<?php
  require_once($_SERVER['DOCUMENT_ROOT']."/global/includes.php");
  check_partial_include(__FILE__);
?>
<h1>Sign Up</h1>
<?php echo $this->view("form", $currentUser, $params); ?>