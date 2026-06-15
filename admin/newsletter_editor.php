<?php
ob_start();
require_once __DIR__ . '/layout.php';

// Sauvegarder un template depuis l'éditeur de blocs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $name    = trim($_POST['tname'] ?? 'Sans titre');
    $subject = trim($_POST['tsubject'] ?? '');
    $blocks  = json_decode($_POST['blocks_json'] ?? '[]', true) ?: [];
    
    // Générer le HTML depuis les blocs
    $sname  = get_setting('site_name');
    $scolor = '#ed0c0f';
    $sphone = get_setting('site_phone');
    $semail = get_setting('site_email');
    $slogo  = get_setting('site_logo');
    $logo_html = $slogo
        ? '<img src="'.site_url($slogo).'" alt="'.htmlspecialchars($sname).'" style="height:48px;object-fit:contain;">'
        : '<span style="font-size:24px;font-weight:900;color:white;">'.htmlspecialchars($sname).'</span>';

    $blocks_html = '';
    foreach ($blocks as $b) {
        $type = $b['type'] ?? '';
        switch ($type) {
            case 'header':
                $blocks_html .= '<tr><td style="background:'.$scolor.';border-radius:16px 16px 0 0;padding:28px 40px;text-align:center;">'.$logo_html.'</td></tr>';
                break;
            case 'hero':
                $img = htmlspecialchars($b['image'] ?? '');
                $blocks_html .= '<tr><td style="padding:0;background:#222;"><img src="'.$img.'" width="600" style="display:block;width:100%;max-width:600px;" alt=""></td></tr>';
                break;
            case 'text':
                $title   = htmlspecialchars($b['title'] ?? '');
                $content = nl2br(htmlspecialchars($b['content'] ?? ''));
                $bg      = $b['dark'] ?? false ? '#111' : 'white';
                $fc      = $b['dark'] ?? false ? '#ccc'  : '#555';
                $tc      = $b['dark'] ?? false ? 'white' : '#111';
                $blocks_html .= '<tr><td style="background:'.$bg.';padding:32px 40px;">'.
                    ($title ? '<h2 style="font-size:22px;font-weight:800;color:'.$tc.';margin:0 0 14px 0;">'.htmlspecialchars($b['title']).'</h2>' : '').
                    '<p style="font-size:15px;line-height:1.8;color:'.$fc.';margin:0;">'.$content.'</p>'.
                    '</td></tr>';
                break;
            case 'button':
                $label = htmlspecialchars($b['label'] ?? 'En savoir plus');
                $url   = htmlspecialchars($b['url']   ?? site_url('/'));
                $bg    = $b['dark'] ?? false ? '#111' : 'white';
                $blocks_html .= '<tr><td style="background:'.$bg.';padding:8px 40px 32px;text-align:center;">'.
                    '<a href="'.$url.'" style="display:inline-block;background:'.$scolor.';color:white;text-decoration:none;padding:14px 36px;border-radius:10px;font-size:15px;font-weight:800;">'.$label.'</a>'.
                    '</td></tr>';
                break;
            case 'image':
                $img = htmlspecialchars($b['src'] ?? '');
                $blocks_html .= '<tr><td style="padding:0;"><img src="'.$img.'" width="600" style="display:block;width:100%;max-width:600px;" alt=""></td></tr>';
                break;
            case 'cols':
                $c1t = htmlspecialchars($b['c1title'] ?? ''); $c1txt = nl2br(htmlspecialchars($b['c1text'] ?? ''));
                $c2t = htmlspecialchars($b['c2title'] ?? ''); $c2txt = nl2br(htmlspecialchars($b['c2text'] ?? ''));
                $blocks_html .= '<tr><td style="background:white;padding:28px 40px;"><table width="100%" cellpadding="0" cellspacing="0"><tr>'.
                    '<td width="48%" valign="top" style="padding-right:12px;border-right:2px solid #f0f0f0;"><h3 style="font-size:15px;font-weight:800;color:#111;margin:0 0 8px 0;">'.$c1t.'</h3><p style="font-size:13px;color:#666;line-height:1.6;margin:0;">'.$c1txt.'</p></td>'.
                    '<td width="4%"></td>'.
                    '<td width="48%" valign="top" style="padding-left:12px;"><h3 style="font-size:15px;font-weight:800;color:#111;margin:0 0 8px 0;">'.$c2t.'</h3><p style="font-size:13px;color:#666;line-height:1.6;margin:0;">'.$c2txt.'</p></td>'.
                    '</tr></table></td></tr>';
                break;
            case 'divider':
                $color = $b['red'] ?? false ? $scolor : '#f0f0f0';
                $blocks_html .= '<tr><td style="background:white;padding:8px 40px;"><div style="height:2px;background:'.$color.';"></div></td></tr>';
                break;
            case 'footer':
                $blocks_html .= '<tr><td style="background:#111;border-radius:0 0 16px 16px;padding:24px 40px;text-align:center;">'.
                    '<p style="color:white;font-size:14px;font-weight:700;margin:0 0 6px 0;">'.htmlspecialchars($sname).'</p>'.
                    '<p style="color:#555;font-size:12px;margin:0 0 12px 0;">'.htmlspecialchars($sphone).' · '.htmlspecialchars($semail).'</p>'.
                    '<p style="font-size:11px;color:#444;margin:0;">Vous recevez cet email car vous êtes inscrit à notre newsletter.<br>'.
                    '<a href="'.site_url('/unsubscribe.php').'?e={{email}}" style="color:#666;">Se désabonner</a></p>'.
                    '</td></tr>';
                break;
        }
    }

    $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'.
        '<body style="margin:0;padding:0;background:#f0f0ee;font-family:\'Segoe UI\',Arial,sans-serif;">'.
        '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f0ee;padding:24px 0;"><tr><td align="center">'.
        '<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">'.
        $blocks_html.
        '</table></td></tr></table></body></html>';

    if (!empty($_POST['template_id'])) {
        db()->prepare("UPDATE kk_mailing_templates SET name=?,subject=?,body_html=?,updated_at=NOW() WHERE id=?")
           ->execute([$name, $subject, $html, (int)$_POST['template_id']]);
        $saved_id = (int)$_POST['template_id'];
    } else {
        db()->prepare("INSERT INTO kk_mailing_templates(name,subject,body_html) VALUES(?,?,?)")
           ->execute([$name, $subject, $html]);
        $saved_id = (int)db()->lastInsertId();
    }
    header('Location: ' . BASE_URL . '/admin/newsletter_editor.php?id=' . $saved_id . '&saved=1');
    exit;
}

$template_id = (int)($_GET['id'] ?? 0);
$tpl = null;
if ($template_id > 0) {
    $s = db()->prepare('SELECT * FROM kk_mailing_templates WHERE id=?');
    $s->execute([$template_id]); $tpl = $s->fetch();
}
$sname  = get_setting('site_name');
$scolor = '#ed0c0f';
?>
<style>
.ne-wrap{display:grid;grid-template-columns:260px 1fr 320px;gap:0;height:calc(100vh - 56px);overflow:hidden}
.ne-sidebar{background:#1a1a1a;color:white;padding:16px;overflow-y:auto;border-right:1px solid #2a2a2a}
.ne-canvas{background:#f0f0ee;overflow-y:auto;padding:24px}
.ne-props{background:#fafafa;border-left:1.5px solid #f0f0f0;padding:16px;overflow-y:auto}
.ne-email-wrap{max-width:600px;margin:0 auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.15)}

/* Blocs palette */
.bloc-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;background:#2a2a2a;border-radius:8px;cursor:pointer;margin-bottom:6px;border:none;color:white;width:100%;font-family:inherit;font-size:12px;font-weight:600;transition:background .15s;text-align:left}
.bloc-btn:hover{background:#333}
.bloc-btn-ico{font-size:18px;width:24px;text-align:center}

/* Blocs dans canvas */
.ne-block{position:relative;cursor:pointer;border:2px solid transparent;transition:border-color .15s}
.ne-block:hover{border-color:#3b82f6}
.ne-block.selected{border-color:#ed0c0f}
.ne-block-actions{position:absolute;top:4px;right:4px;display:none;gap:4px;z-index:10}
.ne-block.selected .ne-block-actions,.ne-block:hover .ne-block-actions{display:flex}
.ne-block-btn{width:26px;height:26px;border:none;border-radius:6px;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center;font-family:inherit}
.ne-block-btn.del{background:#dc2626;color:white}
.ne-block-btn.up,.ne-block-btn.dn{background:#555;color:white}

/* Props panel */
.prop-group{margin-bottom:14px}
.prop-label{font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;display:block}
.prop-input{width:100%;border:1.5px solid #e8e8e8;border-radius:8px;padding:7px 10px;font-size:12px;font-family:inherit;outline:none}
.prop-input:focus{border-color:#ed0c0f}
.prop-textarea{min-height:80px;resize:vertical}
.prop-check{display:flex;align-items:center;gap:8px;font-size:12px;cursor:pointer;margin-bottom:8px}

.ne-toolbar{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:white;border-bottom:1px solid #f0f0f0;gap:8px}
.ne-toolbar input{flex:1;border:1.5px solid #e8e8e8;border-radius:8px;padding:7px 12px;font-size:13px;outline:none}
.ne-toolbar input:focus{border-color:#ed0c0f}
</style>

<div class="ne-toolbar" style="position:fixed;top:0;right:0;left:200px;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.06);">
  <a href="<?= BASE_URL ?>/admin/newsletter.php?tab=templates" style="font-size:12px;color:#aaa;text-decoration:none;">← Templates</a>
  <input type="text" id="tname" placeholder="Nom du template" value="<?= h($tpl['name'] ?? '') ?>">
  <input type="text" id="tsubject" placeholder="Sujet de l'email" value="<?= h($tpl['subject'] ?? '') ?>" style="flex:2;">
  <?php if(isset($_GET['saved'])): ?><span style="color:#15803d;font-size:12px;font-weight:700;">✅ Sauvé</span><?php endif; ?>
  <button onclick="saveTemplate()" style="background:#ed0c0f;color:white;border:none;border-radius:8px;padding:8px 18px;font-size:13px;font-weight:700;cursor:pointer;">💾 Sauvegarder</button>
  <button onclick="previewEmail()" style="background:#111;color:white;border:none;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:700;cursor:pointer;">👁 Aperçu</button>
</div>

<div class="ne-wrap" style="margin-top:50px;">
  <!-- Palette blocs -->
  <div class="ne-sidebar">
    <div style="font-size:11px;font-weight:700;color:#666;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">Blocs à ajouter</div>
    <?php foreach([
      ['header',  '🏠', 'En-tête avec logo'],
      ['hero',    '🖼️', 'Image pleine largeur'],
      ['text',    '📝', 'Texte / Titre'],
      ['button',  '🔴', 'Bouton CTA'],
      ['cols',    '▌▌', '2 colonnes'],
      ['image',   '📷', 'Image'],
      ['divider', '─',  'Séparateur'],
      ['footer',  '🔽', 'Pied de page'],
    ] as [$t, $i, $l]): ?>
    <button class="bloc-btn" onclick="addBlock('<?= $t ?>')">
      <span class="bloc-btn-ico"><?= $i ?></span><?= $l ?>
    </button>
    <?php endforeach; ?>
    <div style="margin-top:20px;font-size:11px;color:#555;line-height:1.6;">
      <strong style="color:#888;">Variables :</strong><br>
      <code style="color:#aaa;">{{name}}</code> Prénom<br>
      <code style="color:#aaa;">{{email}}</code> Email
    </div>
  </div>

  <!-- Canvas email -->
  <div class="ne-canvas" id="canvas">
    <div class="ne-email-wrap" id="email-blocks">
      <!-- Les blocs sont rendus ici -->
      <div id="empty-msg" style="padding:60px 40px;text-align:center;color:#aaa;">
        <div style="font-size:40px;margin-bottom:12px;">✉️</div>
        <div style="font-size:14px;font-weight:600;">Ajoutez des blocs depuis la palette de gauche</div>
      </div>
    </div>
  </div>

  <!-- Propriétés -->
  <div class="ne-props" id="props-panel">
    <div style="font-size:12px;color:#aaa;text-align:center;padding:30px 0;" id="props-empty">
      <div style="font-size:28px;margin-bottom:8px;">✏️</div>
      Cliquez sur un bloc pour l'éditer
    </div>
    <div id="props-content" style="display:none;"></div>
  </div>
</div>

<form method="POST" id="save-form" style="display:none;">
  <input type="hidden" name="save_template" value="1">
  <input type="hidden" name="tname" id="f-name">
  <input type="hidden" name="tsubject" id="f-subject">
  <input type="hidden" name="blocks_json" id="f-blocks">
  <input type="hidden" name="template_id" value="<?= $template_id ?>">
</form>

<script>
var blocks   = [];
var selIdx   = -1;
var scolor   = '<?= $scolor ?>';
var sname    = '<?= h($sname) ?>';
var logoHtml = '<?= h(get_setting('site_logo') ? '<img src="'.site_url(get_setting('site_logo')).'" style="height:48px;object-fit:contain;" alt="">' : '<span style="font-size:22px;font-weight:900;color:white;">'.h($sname).'</span>') ?>';

// Charger template existant si dispo
<?php if($tpl): ?>
(function() {
  // On ne peut pas récupérer les blocs depuis le HTML généré, mais on peut charger un état initial minimal
  // Pour un template existant, on affiche un bloc texte avec le contenu
  blocks = [{type:'header'},{type:'text',title:'<?= addslashes(h($sname)) ?> — Newsletter',content:'Bonjour {{name}},\n\nRetrouvez nos actualités.','dark':false},{type:'button',label:'Découvrir →',url:'<?= site_url('/') ?>','dark':false},{type:'footer'}];
  renderAll();
})();
<?php endif; ?>

function addBlock(type) {
  var defaults = {
    header:  {},
    hero:    {image:'https://via.placeholder.com/600x250/222222/ed0c0f?text=Image'},
    text:    {title:'Titre de la section',content:'Votre texte ici...',dark:false},
    button:  {label:'Découvrir →',url:'<?= site_url('/') ?>',dark:false},
    cols:    {c1title:'Titre 1',c1text:'Texte colonne 1',c2title:'Titre 2',c2text:'Texte colonne 2'},
    image:   {src:'https://via.placeholder.com/600x200/f5f5f5/999?text=Image'},
    divider: {red:false},
    footer:  {},
  };
  blocks.push(Object.assign({type:type}, defaults[type]||{}));
  renderAll();
  selectBlock(blocks.length-1);
}

function renderAll() {
  var wrap = document.getElementById('email-blocks');
  document.getElementById('empty-msg').style.display = blocks.length ? 'none' : 'block';
  // Supprimer les anciens blocs (garder #empty-msg)
  var old = wrap.querySelectorAll('.ne-block');
  old.forEach(function(el){el.remove();});

  blocks.forEach(function(b, i) {
    var div = document.createElement('div');
    div.className = 'ne-block' + (i===selIdx?' selected':'');
    div.dataset.idx = i;
    div.onclick = function(e){ e.stopPropagation(); selectBlock(i); };
    div.innerHTML = renderBlock(b, i);
    div.insertAdjacentHTML('afterbegin',
      '<div class="ne-block-actions">'
      +(i>0?'<button class="ne-block-btn up" onclick="moveBlock('+i+',-1,event)">↑</button>':'')
      +(i<blocks.length-1?'<button class="ne-block-btn dn" onclick="moveBlock('+i+',1,event)">↓</button>':'')
      +'<button class="ne-block-btn del" onclick="delBlock('+i+',event)">×</button>'
      +'</div>'
    );
    wrap.appendChild(div);
  });
}

function renderBlock(b, i) {
  switch(b.type) {
    case 'header':
      return '<div style="background:'+scolor+';padding:24px 40px;text-align:center;">'+logoHtml+'</div>';
    case 'hero':
      return '<img src="'+(b.image||'')+'" style="display:block;width:100%;max-height:250px;object-fit:cover;">';
    case 'text':
      var bg = b.dark?'#111':'white'; var fc = b.dark?'#ccc':'#555'; var tc = b.dark?'white':'#111';
      return '<div style="background:'+bg+';padding:28px 40px;">'
        +(b.title?'<h2 style="font-size:20px;font-weight:800;color:'+tc+';margin:0 0 12px 0;">'+esc(b.title)+'</h2>':'')
        +'<p style="font-size:14px;line-height:1.8;color:'+fc+';margin:0;white-space:pre-wrap;">'+esc(b.content||'')+'</p></div>';
    case 'button':
      var bg2 = b.dark?'#111':'white';
      return '<div style="background:'+bg2+';padding:8px 40px 28px;text-align:center;"><span style="display:inline-block;background:'+scolor+';color:white;padding:13px 34px;border-radius:10px;font-size:15px;font-weight:800;">'+esc(b.label||'Bouton')+'</span></div>';
    case 'cols':
      return '<div style="background:white;padding:24px 40px;"><div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">'
        +'<div style="padding-right:10px;border-right:2px solid #f0f0f0;"><strong>'+esc(b.c1title||'')+'</strong><p style="font-size:12px;color:#666;margin:6px 0 0;">'+esc(b.c1text||'')+'</p></div>'
        +'<div><strong>'+esc(b.c2title||'')+'</strong><p style="font-size:12px;color:#666;margin:6px 0 0;">'+esc(b.c2text||'')+'</p></div>'
        +'</div></div>';
    case 'image':
      return '<img src="'+(b.src||'')+'" style="display:block;width:100%;object-fit:cover;">';
    case 'divider':
      return '<div style="background:white;padding:8px 40px;"><div style="height:2px;background:'+(b.red?scolor:'#f0f0f0')+'"></div></div>';
    case 'footer':
      return '<div style="background:#111;padding:22px 40px;text-align:center;">'
        +'<p style="color:white;font-size:14px;font-weight:700;margin:0 0 6px 0;">'+esc(sname)+'</p>'
        +'<p style="font-size:11px;color:#555;margin:8px 0 0 0;">Vous recevez cet email car vous êtes inscrit à notre newsletter. <a href="#" style="color:#666;">Se désabonner</a></p>'
        +'</div>';
    default: return '<div style="padding:20px;background:#f5f5f5;color:#aaa;text-align:center;">'+b.type+'</div>';
  }
}

function esc(s) { var d=document.createElement('div');d.textContent=s;return d.innerHTML.replace(/\n/g,'<br>'); }

function selectBlock(i) {
  selIdx = i;
  renderAll();
  renderProps(blocks[i], i);
}

function renderProps(b, i) {
  document.getElementById('props-empty').style.display = 'none';
  document.getElementById('props-content').style.display = 'block';
  var html = '<div style="font-size:12px;font-weight:700;color:#111;margin-bottom:14px;">✏️ Éditer — ' + b.type + '</div>';
  
  switch(b.type) {
    case 'text':
      html += prop('Titre','text','b.title',b.title||'')
            + prop('Contenu','textarea','b.content',b.content||'')
            + propCheck('Fond sombre','b.dark',b.dark); break;
    case 'button':
      html += prop('Label du bouton','text','b.label',b.label||'')
            + prop('URL','text','b.url',b.url||'')
            + propCheck('Fond sombre','b.dark',b.dark); break;
    case 'hero': case 'image':
      var k = b.type==='hero'?'b.image':'b.src';
      html += prop('URL de l\'image','text',k,b[b.type==='hero'?'image':'src']||''); break;
    case 'cols':
      html += prop('Titre colonne 1','text','b.c1title',b.c1title||'')
            + prop('Texte colonne 1','textarea','b.c1text',b.c1text||'')
            + prop('Titre colonne 2','text','b.c2title',b.c2title||'')
            + prop('Texte colonne 2','textarea','b.c2text',b.c2text||''); break;
    case 'divider':
      html += propCheck('Couleur rouge','b.red',b.red); break;
    default:
      html += '<p style="color:#aaa;font-size:12px;">Pas de propriétés.</p>';
  }
  document.getElementById('props-content').innerHTML = html;

  // Bind events
  document.getElementById('props-content').querySelectorAll('[data-key]').forEach(function(inp) {
    inp.addEventListener('input', function() {
      var keys = this.dataset.key.split('.');
      var obj  = blocks[selIdx];
      for (var k=1; k<keys.length-1; k++) obj = obj[keys[k]];
      var last = keys[keys.length-1];
      obj[last] = this.type==='checkbox' ? this.checked : this.value;
      // Re-render juste ce bloc
      var blkEls = document.querySelectorAll('.ne-block');
      if (blkEls[selIdx]) {
        var content = blkEls[selIdx].querySelector('.ne-block-actions');
        blkEls[selIdx].innerHTML = (content ? content.outerHTML : '') + renderBlock(blocks[selIdx], selIdx);
      }
    });
  });
}

function prop(label, type, key, val) {
  if (type==='textarea') return '<div class="prop-group"><label class="prop-label">'+label+'</label><textarea class="prop-input prop-textarea" data-key="'+key+'">'+val+'</textarea></div>';
  return '<div class="prop-group"><label class="prop-label">'+label+'</label><input type="text" class="prop-input" data-key="'+key+'" value="'+val.replace(/"/g,'&quot;')+'"></div>';
}
function propCheck(label, key, val) {
  return '<label class="prop-check"><input type="checkbox" data-key="'+key+'" '+(val?'checked':'')+'> '+label+'</label>';
}

function moveBlock(i, dir, e) { e.stopPropagation(); var j=i+dir; if(j<0||j>=blocks.length)return; var tmp=blocks[i];blocks[i]=blocks[j];blocks[j]=tmp; selIdx=j; renderAll(); renderProps(blocks[j],j); }
function delBlock(i, e) { e.stopPropagation(); blocks.splice(i,1); selIdx=-1; document.getElementById('props-empty').style.display='block'; document.getElementById('props-content').style.display='none'; renderAll(); }

function saveTemplate() {
  document.getElementById('f-name').value    = document.getElementById('tname').value;
  document.getElementById('f-subject').value = document.getElementById('tsubject').value;
  document.getElementById('f-blocks').value  = JSON.stringify(blocks);
  document.getElementById('save-form').submit();
}

function previewEmail() {
  var html = '<!DOCTYPE html><html><body style="background:#f0f0ee;margin:0;padding:20px;font-family:sans-serif;">'
    + '<table width="600" cellpadding="0" cellspacing="0" style="margin:0 auto;">';
  blocks.forEach(function(b){ html += '<tr><td>' + renderBlock(b) + '</td></tr>'; });
  html += '</table></body></html>';
  var w = window.open('','_preview','width=640,height=700');
  w.document.write(html); w.document.close();
}

document.getElementById('email-blocks').addEventListener('click', function(e) {
  if (!e.target.closest('.ne-block')) { selIdx=-1; document.getElementById('props-empty').style.display='block'; document.getElementById('props-content').style.display='none'; document.querySelectorAll('.ne-block').forEach(function(el){el.classList.remove('selected');}); }
});
</script>
<?php require_once __DIR__ . '/layout_end.php'; ?>
