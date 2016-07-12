<?php
/* v1.0 2015.10.1 lyhyuer@qq.com */
require_once '../class/dz_frame_inti_core.php';
C::app()->init_setting = true;
C::app()->init_misc = false;
C::app()->init();
print_r($_G);exit;
?>
