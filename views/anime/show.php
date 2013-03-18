<?php
  require_once($_SERVER['DOCUMENT_ROOT']."/global/includes.php");
  $this->app->check_partial_include(__FILE__);
?>
     <div class='row-fluid'>
        <div class='span3 userProfileColumn leftColumn'>
          <ul class='thumbnails avatarContainer'>
            <li class='span12'>
              <div class='thumbnail profileAvatar'>
<?php
  if ($this->imagePath() != '') {
?>                <?php echo $this->imageTag(array('class' => 'img-rounded', 'alt' => '')); ?>
<?php
  } else {
?>                <img src='/img/anime/blank.png' class='img-rounded' alt=''>
<?php
  }
?>          </div>
            </li>
          </ul>
          <div>
            <h2>Tags:</h2>
            <?php echo $this->tagList($this->app->user); ?>
          </div>
        </div>
        <div class='span9 userProfileColumn rightColumn'>
          <div class='profileUserInfo'>
            <h1>
              <?php echo escape_output($this->title()); ?>
              <?php echo $this->allow($this->app->user, "edit") ? "<small>(".$this->link("edit", "edit").")</small>" : ""; ?>
            </h1>

            <ul class="nav nav-tabs">
              <li class="active">
                <a href="#generalInfo" data-toggle="tab">General</a>
              </li>
              <li>
                <a href="#relatedAnime" data-toggle="tab">Related</a>
              </li>
            </ul>
            <div class='tab-content'>
              <div class='tab-pane active' id='generalInfo'>
                <p>
                  <?php echo escape_output($this->description()); ?>
                </p>
<?php
  if ($this->app->user->loggedIn()) {
?>
                <ul class='thumbnails'>
                  <li class='span4'>
                    <p class='lead'>Global Average:</p>
                    <?php echo $this->scoreBar($this->ratingAvg()); ?>
                  </li>
                  <li class='span4'>
<?php
    if (!isset($this->app->user->animeList()->uniqueList()[$this->id]) || $this->app->user->animeList()->uniqueList()[$this->id]['score'] == 0) {
      $userRating = $this->app->recsEngine->predict($this->app->user, $this)[$this->id];
?>
                    <p class='lead'>Predicted score:</p>
                    <?php echo $this->scoreBar($userRating); ?>
<?php
    } else {
      $userRating = $this->app->user->animeList()->uniqueList()[$this->id]['score'];
?>
                    <p class='lead'>You rated this:</p>
                    <?php echo $this->scoreBar($userRating); ?>
<?php
    }
    if ($userRating != 0) {
?>
<p><small>(<?php echo abs(round($userRating - $this->app->user->animeList()->uniqueListAvg(), 2))." points ".($userRating > $this->app->user->animeList()->uniqueListAvg() ? "higher" : "lower")." than your average)"; ?></small></p>
<?php
    }
  } else {
?>
                <ul class='thumbnails'>
                  <li class='span4'>
                    <p class='lead'>Predicted score:</p>
                    <p>Sign in to view your predicted score!</p>
<?php
  }
?>
                  </li>
<?php /*
                  <li class='span8'>
                    <p class='lead'>Tags:</p>
                    <?php echo $this->tagCloud($this->app->user); ?>
                  </li>
*/ ?>
                </ul>
              </div>
              <div class='tab-pane' id='relatedAnime'>
                <h2>Related series:</h2>
                <ul class="item-grid recommendations">
<?php
  foreach ($this->similar(8)->load('info') as $anime) {
?>
                  <li><?php echo $anime->link("show", "<h4 title='".escape_output($anime->title)."'>".escape_output($anime->title)."</h4>".$anime->imageTag(array('title' => $anime->description(True))), Null, True); ?></li>
<?php
  }
?>
                </ul>
              </div>
            </div>
            <div id='userFeed'>
<?php
  if ($this->app->user->loggedIn()) {
    $anime = new Anime($this->app, 0);
    if (isset($this->app->user->animeList()->uniqueList()[$this->id])) {
      $thisEntry = $this->app->user->animeList()->uniqueList()[$this->id];
      $addText = "Update this anime in your list: ";
    } else {
      $thisEntry = [];
      $addText = "Add this anime to your list: ";
    }
?>
              <div class='addListEntryForm'>
                <?php echo $this->app->form(array('action' => $this->app->user->animeList()->url("new", Null, array('user_id' => intval($this->app->user->id))), 'class' => 'form-inline')); ?>
                  <input name='anime_list[user_id]' id='anime_list_user_id' type='hidden' value='<?php echo intval($this->app->user->id); ?>' />
                  <?php echo $addText; ?>
                  <input name='anime_list[anime_id]' id='anime_list_anime_id' type='hidden' value='<?php echo intval($this->id); ?>' />
                  <?php echo display_status_dropdown("anime_list[status]", "span3", $thisEntry['status'] ? $thisEntry['status'] : 1); ?>
                  <div class='input-append'>
                    <input class='input-mini' name='anime_list[score]' id='anime_list_score' type='number' min='0' max='10' step='1' value='<?php echo intval($thisEntry['score']) == 0 ? "" : intval($thisEntry['score']); ?>' />
                    <span class='add-on'>/10</span>
                  </div>
                  <div class='input-prepend'>
                    <span class='add-on'>Ep</span>
                    <input class='input-mini' name='anime_list[episode]' id='anime_list_episode' type='number' min='0' step='1' value='<?php echo intval($thisEntry['episode']); ?>' />
                  </div>
                  <input type='submit' class='btn btn-primary updateEntryButton' value='Update' />
                </form>
              </div>
<?php
  }
?>
           <?php echo $this->app->user->view('feed', $params); ?>
          </div>
        </div>
      </div>
    </div>