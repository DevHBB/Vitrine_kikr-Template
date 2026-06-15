<?php
require_once __DIR__ . '/config.php';
$t = $_GET['t'] ?? '';
if ($t) {
    try {
        $s = db()->prepare("SELECT id,campaign_id FROM kk_campaign_sends WHERE token=? AND opened=0");
        $s->execute([$t]);
        $row = $s->fetch();
        if ($row) {
            db()->prepare("UPDATE kk_campaign_sends SET opened=1,opened_at=NOW() WHERE id=?")->execute([$row['id']]);
            db()->prepare("UPDATE kk_campaigns SET total_open=total_open+1 WHERE id=?")->execute([$row['campaign_id']]);
        }
    } catch(Exception $e) {}
}
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
exit;
