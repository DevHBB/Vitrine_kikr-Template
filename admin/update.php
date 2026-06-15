<?php
ob_start();
require_once __DIR__ . '/layout.php';

// ─────────────────────────────────────────────
// CONSTANTES
// ─────────────────────────────────────────────
$REPO     = KIKR_REPO;
$API_URL  = KIKR_GITHUB_API;
$ROOT     = dirname(__DIR__);           // C:\xampp\htdocs\kikr3
$TMP_DIR  = sys_get_temp_dir();

// Dossiers/fichiers à NE JAMAIS écraser (données utilisateur)
$PROTECTED = [
    'install/config.ini',
    'img/',
    'data/',
    'version.php',          // on le met à jour manuellement après
];

// ─────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────
function github_get(string $url): ?array {
    $ctx = stream_context_create(['http' => [
        'method'  => 'GET',
        'header'  => "User-Agent: KikrCMS/1.0\r\nAccept: application/vnd.github+json\r\n",
        'timeout' => 10,
    ]]);
    $json = @file_get_contents($url, false, $ctx);
    return $json ? json_decode($json, true) : null;
}

function is_protected(string $rel_path, array $protected): bool {
    foreach ($protected as $p) {
        if (strpos($rel_path, $p) === 0) return true;
    }
    return false;
}

function extract_zip_safe(string $zip_path, string $dest, array $protected, string $root): array {
    $zip = new ZipArchive();
    if ($zip->open($zip_path) !== true) return ['ok' => false, 'msg' => "Impossible d'ouvrir le ZIP."];

    $skipped  = [];
    $updated  = [];
    $root_dir = ''; // Dossier racine dans le ZIP (ex: "kikr3-1.2.0/")

    // Détecter le dossier racine du ZIP
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (substr_count(rtrim($name, '/'), '/') === 0 && substr($name, -1) === '/') {
            $root_dir = $name;
            break;
        }
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name    = $zip->getNameIndex($i);
        $rel     = $root_dir ? substr($name, strlen($root_dir)) : $name;
        if (!$rel || substr($rel, -1) === '/') continue; // dossier vide

        if (is_protected($rel, $protected)) {
            $skipped[] = $rel;
            continue;
        }

        $target = $dest . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $dir    = dirname($target);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $content = $zip->getFromIndex($i);
        file_put_contents($target, $content);
        $updated[] = $rel;
    }

    $zip->close();
    return ['ok' => true, 'updated' => $updated, 'skipped' => $skipped];
}

// ─────────────────────────────────────────────
// ACTION : Vérifier la mise à jour
// ─────────────────────────────────────────────
$latest       = null;
$check_error  = null;
$update_log   = null;
$update_ok    = false;
$current_ver  = KIKR_VERSION;

if (isset($_GET['check']) || isset($_POST['do_update'])) {
    $latest = github_get($API_URL);
    if (!$latest) {
        $check_error = "Impossible de contacter GitHub. Vérifiez votre connexion internet.";
    }
}

// ─────────────────────────────────────────────
// ACTION : Appliquer la mise à jour
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_update']) && $latest) {
    $tag        = $latest['tag_name']   ?? '';
    $zip_url    = $latest['zipball_url'] ?? '';

    if (!$tag || !$zip_url) {
        $update_log = ['error' => 'Release GitHub invalide (pas de tag ou de ZIP).'];
    } else {
        $zip_file = $TMP_DIR . DIRECTORY_SEPARATOR . 'kikr_update_' . preg_replace('/[^a-z0-9\.\-]/i', '', $tag) . '.zip';

        // Télécharger le ZIP
        $ctx = stream_context_create(['http' => [
            'method'     => 'GET',
            'header'     => "User-Agent: KikrCMS/1.0\r\n",
            'timeout'    => 60,
            'follow_location' => true,
        ]]);
        $zip_data = @file_get_contents($zip_url, false, $ctx);

        if (!$zip_data || strlen($zip_data) < 1000) {
            $update_log = ['error' => 'Téléchargement échoué ou fichier vide. Réessayez.'];
        } else {
            file_put_contents($zip_file, $zip_data);

            // Extraire en protégeant les données
            $result = extract_zip_safe($zip_file, $ROOT, $PROTECTED, $ROOT);
            @unlink($zip_file);

            if (!$result['ok']) {
                $update_log = ['error' => $result['msg']];
            } else {
                // Mettre à jour version.php
                $new_version_content = "<?php\n// Version actuelle du CMS — mise à jour automatiquement lors des updates\ndefine('KIKR_VERSION', " . var_export($tag, true) . ");\ndefine('KIKR_REPO',    '" . KIKR_REPO . "');\ndefine('KIKR_GITHUB_API', 'https://api.github.com/repos/' . KIKR_REPO . '/releases/latest');\n";
                file_put_contents($ROOT . '/version.php', $new_version_content);

                // Logger la mise à jour
                set_setting('last_update_version', $tag);
                set_setting('last_update_date',    date('Y-m-d H:i:s'));

                $update_ok  = true;
                $update_log = [
                    'tag'     => $tag,
                    'updated' => $result['updated'],
                    'skipped' => $result['skipped'],
                ];
            }
        }
    }
}
?>

<style>
.update-card{background:white;border-radius:16px;padding:28px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:16px}
.update-version-badge{display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:20px;font-size:14px;font-weight:800;letter-spacing:-.3px}
.update-version-badge.current{background:#f5f5f3;color:#555}
.update-version-badge.latest{background:#dcfce7;color:#15803d}
.update-version-badge.outdated{background:#fef9c3;color:#854d0e}
.update-version-badge.error{background:#fee2e2;color:#dc2626}
.update-step{display:flex;align-items:flex-start;gap:14px;padding:14px;border-radius:12px;background:#f9f9f9;margin-bottom:8px}
.update-step-ico{font-size:22px;flex-shrink:0;margin-top:2px}
.update-step-title{font-size:13px;font-weight:700;margin-bottom:2px}
.update-step-desc{font-size:12px;color:#888;line-height:1.6}
.update-log{background:#111;border-radius:12px;padding:16px;font-family:'Courier New',monospace;font-size:11px;color:#aaa;max-height:240px;overflow-y:auto;margin-top:12px}
.update-log .ok{color:#4ade80}
.update-log .skip{color:#fbbf24}
.update-log .err{color:#f87171}
.update-log .hd{color:white;font-weight:700;margin-bottom:8px;display:block}
.protected-list{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.protected-tag{background:#f0fdf4;border:1px solid #86efac;color:#15803d;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:600;font-family:monospace}
</style>

<div class="adm-topbar">
  <h1>🔄 Mise à jour du CMS</h1>
  <a href="https://github.com/<?= h($REPO) ?>/releases" target="_blank" class="btn btn-secondary btn-sm">
    Voir les releases GitHub →
  </a>
</div>
<div class="adm-content">

<?php if($update_ok && $update_log): ?>
<!-- ✅ SUCCÈS -->
<div style="background:#f0fdf4;border:2px solid #86efac;border-radius:16px;padding:24px;margin-bottom:16px;">
  <div style="font-size:20px;font-weight:900;color:#15803d;margin-bottom:6px;">✅ Mise à jour <?= h($update_log['tag']) ?> installée !</div>
  <p style="font-size:13px;color:#166534;margin-bottom:16px;">Le site a été mis à jour avec succès. Vos données (images, config) ont été préservées.</p>
  <div class="update-log">
    <span class="hd">// Journal de mise à jour</span>
    <?php foreach(($update_log['updated'] ?? []) as $f): ?>
    <div class="ok">✓ <?= h($f) ?></div>
    <?php endforeach; ?>
    <?php foreach(($update_log['skipped'] ?? []) as $f): ?>
    <div class="skip">⊘ Protégé (ignoré) : <?= h($f) ?></div>
    <?php endforeach; ?>
  </div>
  <a href="<?= BASE_URL ?>/admin/update.php" class="btn btn-primary" style="margin-top:16px;display:inline-block;">← Retour</a>
</div>

<?php elseif(isset($update_log['error'])): ?>
<!-- ❌ ERREUR -->
<div style="background:#fef2f2;border:2px solid #fecaca;border-radius:16px;padding:20px;margin-bottom:16px;">
  <div style="font-size:16px;font-weight:800;color:#dc2626;margin-bottom:6px;">❌ Erreur</div>
  <p style="font-size:13px;color:#991b1b;"><?= h($update_log['error']) ?></p>
  <a href="<?= BASE_URL ?>/admin/update.php" class="btn btn-secondary" style="margin-top:12px;display:inline-block;">← Réessayer</a>
</div>

<?php else: ?>

<!-- VERSION ACTUELLE -->
<div class="update-card">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
      <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:8px;">Version installée</div>
      <div class="update-version-badge current">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
        <?= h($current_ver) ?>
      </div>
      <div style="font-size:11px;color:var(--muted);margin-top:8px;">
        Repo : <a href="https://github.com/<?= h($REPO) ?>" target="_blank" style="color:#ed0c0f;"><?= h($REPO) ?></a>
        <?php $lu = get_setting('last_update_date'); if($lu): ?>
        · Dernière mise à jour : <?= date('d/m/Y à H:i', strtotime($lu)) ?>
        <?php endif; ?>
      </div>
    </div>
    <a href="?check=1" class="btn btn-dark">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="margin-right:6px;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
      Vérifier les mises à jour
    </a>
  </div>
</div>

<?php if($check_error): ?>
<div style="background:#fef2f2;border:1.5px solid #fecaca;border-radius:12px;padding:16px;margin-bottom:16px;">
  <strong style="color:#dc2626;">⚠️ Erreur réseau</strong>
  <p style="font-size:13px;color:#991b1b;margin-top:4px;"><?= h($check_error) ?></p>
</div>

<?php elseif($latest): ?>
<?php
  $latest_tag    = $latest['tag_name']    ?? '?';
  $latest_name   = $latest['name']        ?? $latest_tag;
  $latest_body   = $latest['body']        ?? '';
  $latest_date   = isset($latest['published_at']) ? date('d/m/Y', strtotime($latest['published_at'])) : '?';
  $up_to_date    = version_compare(ltrim($current_ver,'v'), ltrim($latest_tag,'v'), '>=');
?>
<!-- RÉSULTAT CHECK -->
<div class="update-card">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div>
      <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:8px;">Dernière release disponible</div>
      <div class="update-version-badge <?= $up_to_date ? 'latest' : 'outdated' ?>">
        <?= $up_to_date ? '✅' : '🆕' ?> <?= h($latest_tag) ?>
      </div>
      <div style="font-size:12px;color:var(--muted);margin-top:8px;">Publiée le <?= $latest_date ?></div>
      <?php if(!$up_to_date): ?>
      <div style="font-size:13px;font-weight:700;color:#854d0e;margin-top:8px;">
        ⚡ Une nouvelle version est disponible !
      </div>
      <?php else: ?>
      <div style="font-size:13px;font-weight:700;color:#15803d;margin-top:8px;">
        ✅ Votre site est à jour.
      </div>
      <?php endif; ?>
    </div>
    <?php if(!$up_to_date): ?>
    <form method="POST">
      <input type="hidden" name="check" value="1">
      <button type="submit" name="do_update" value="1"
              class="btn btn-primary btn-lg"
              onclick="return confirm('Installer la version <?= h($latest_tag) ?> ?\n\nVos images, configuration et données seront préservées.\nLe site restera accessible pendant la mise à jour.')"
              style="background:#15803d;box-shadow:0 4px 16px rgba(21,128,61,.3);">
        ⬇️ Installer <?= h($latest_tag) ?> maintenant
      </button>
    </form>
    <?php endif; ?>
  </div>

  <?php if($latest_body): ?>
  <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0;">
    <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:8px;">📋 Notes de version</div>
    <div style="font-size:13px;line-height:1.7;color:#555;background:#f9f9f9;border-radius:8px;padding:12px;white-space:pre-wrap;"><?= h($latest_body) ?></div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- INFO PROTECTION DES DONNÉES -->
<div class="update-card">
  <div style="font-size:14px;font-weight:800;margin-bottom:14px;">🛡️ Données protégées lors des mises à jour</div>
  <p style="font-size:13px;color:#666;margin-bottom:12px;line-height:1.6;">
    Les fichiers et dossiers suivants ne sont <strong>jamais touchés</strong> lors d'une mise à jour automatique,
    même s'ils ont changé dans la nouvelle version.
  </p>
  <div class="protected-list">
    <?php foreach($PROTECTED as $p): ?>
    <span class="protected-tag">📁 <?= h($p) ?></span>
    <?php endforeach; ?>
  </div>
  <div style="font-size:11px;color:var(--muted);margin-top:12px;line-height:1.6;">
    Cela inclut vos images uploadées, votre configuration base de données, vos logos, vos PDF uploadés, et votre fichier de version.
  </div>
</div>

<!-- COMMENT PUBLIER UNE RELEASE -->
<div class="update-card" style="border:1.5px solid #f0f0f0;">
  <div style="font-size:14px;font-weight:800;margin-bottom:12px;">🚀 Comment publier une nouvelle version sur GitHub</div>
  <div style="display:flex;flex-direction:column;gap:8px;">
    <?php foreach([
      ['1', 'Pushez votre code', 'git commit + git push sur le repo DevHBB/Vitrine_kikr-Template'],
      ['2', 'Créez un tag', 'git tag v1.2.0 && git push origin v1.2.0'],
      ['3', 'Publiez une release', 'Sur GitHub → Releases → "Draft a new release" → choisir le tag → Publish'],
      ['4', 'Déclencher la MAJ', 'Revenez ici et cliquez "Vérifier les mises à jour" → Installer'],
    ] as [$n,$t,$d]): ?>
    <div class="update-step">
      <div class="update-step-ico" style="background:#111;color:white;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:900;"><?= $n ?></div>
      <div><div class="update-step-title"><?= $t ?></div><div class="update-step-desc"><code style="background:#f5f5f3;padding:2px 6px;border-radius:4px;font-size:11px;"><?= $d ?></code></div></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php endif; ?>
</div>
<?php require_once __DIR__ . '/layout_end.php'; ?>
