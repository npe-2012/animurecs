<?php
  require_once($_SERVER['DOCUMENT_ROOT']."/global/includes.php");
  check_partial_include(__FILE__);
?>
      <h1>All Users</h1>
      <table class='table table-striped table-bordered dataTable'>
        <thead>
          <tr>
            <th>Username</th>
            <th>Role</th>
            <th></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
<?php
  $users = $this->dbConn->stdQuery("SELECT `users`.`id` FROM `users` ORDER BY `users`.`username` ASC");
  while ($thisID = $users->fetch_assoc()) {
    $thisUser = new User($this->dbConn, intval($thisID['id']));
?>          <tr>
            <td><?php echo $thisUser->link("show", $thisUser->username()); ?></td>
            <td><?php echo escape_output(convert_usermask_to_text($thisUser->usermask())); ?></td>
            <td><?php echo $params['user']->isAdmin() ? $thisUser->link("edit", "Edit") : ""; ?></td>
            <td><?php echo $params['user']->isAdmin() ? $thisUser->link("delete", "Delete"): ""; ?></td>
          </tr>
<?php
  }
?>
        </tbody>
      </table>