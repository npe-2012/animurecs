<?php
  require_once($_SERVER['DOCUMENT_ROOT']."/global/includes.php");
  $this->app->check_partial_include(__FILE__);

  // lists all anime.
  $newAnime = new Anime($this->app, 0);
?>
<h1>Browse Anime</h1>
<?php echo $newAnime->view('searchForm'); ?>
<?php echo paginate($newAnime->url("index", Null, array('search' => $_REQUEST['search'], 'page' => '')), intval($this->app->page), $params['numPages']); ?>
<table class='table table-striped table-bordered dataTable' data-recordsPerPage='<?php echo $params['resultsPerPage']; ?>'>
  <thead>
    <tr>
      <th>Title</th>
      <th>Description</th>
      <th>Length</th>
<?php
  if ($newAnime->allow($this->app->user, 'edit')) {
?>
      <th></th>
<?php
  }
  if ($newAnime->allow($this->app->user, 'delete')) {
?>
      <th></th>
<?php
  }
?>
    </tr>
  </thead>
  <tbody>
<?php
  foreach ($params['anime'] as $thisAnime) {
?>
    <tr>
      <td><?php echo $thisAnime->link("show", $thisAnime->title()); ?></td>
      <td><?php echo escape_output($thisAnime->description()); ?></td>
      <td><?php echo intval($thisAnime->episodeCount() * $thisAnime->episodeLength()); ?> minutes</td>
<?php
    if ($newAnime->allow($this->app->user, 'edit')) { 
?>
      <td><?php echo $thisAnime->link("edit", "Edit"); ?></td>
<?php
    }
    if ($newAnime->allow($this->app->user, 'delete')) { 
?>
      <td><?php echo $thisAnime->link("delete", "Delete"); ?></td>
<?php
    }
?>
    </tr>
<?php
  }
?>
  </tbody>
</table>
<?php echo paginate($newAnime->url("index", Null, array('search' => $_REQUEST['search'], 'page' => '')), intval($this->app->page), $params['numPages']); ?>
<?php echo $newAnime->allow($this->app->user, 'new') ? $newAnime->link("new", "Add an anime") : ""; ?>