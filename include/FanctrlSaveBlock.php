<?php
file_put_contents('/tmp/fanctrlplus_debug.log', "[" . date('Y-m-d H:i:s') . "] FanctrlSaveBlock TEST reached\n", FILE_APPEND);
header('Content-Type: application/json');
echo json_encode(['status' => 'ok']);
exit;
