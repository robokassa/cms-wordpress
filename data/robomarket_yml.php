<?php
header('Content-Type: application/xml');
header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename=robomarket'.date("d_m_Y_His").'.yml.xml');
header('Connection: Keep-Alive');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
echo '<?xml version="1.0" encoding="utf-8"?>';