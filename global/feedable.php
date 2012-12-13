<?php

trait Feedable {
  // allows an object to assemble and display a formatted feed of entries belonging to this object.

  // any feedable class must define a way to retrieve entries (from the database, presumably)
  abstract protected function getEntries();
  
  public function entries(DateTime $maxTime=Null, $limit=Null) {
    // returns a list of feed entries, up to $maxTime and with at most $limit entries.
    // feed entries contain at a minimum an object, time and user field.

    if ($this->entries === Null) {
      $this->entries = $this->getEntries();
    }
    if ($maxTime !== Null || $limit !== Null) {
      // Returns a list of up to $limit entries up to $maxTime.
      $serverTimezone = new DateTimeZone(Config::SERVER_TIMEZONE);
      $outputTimezone = new DateTimeZone(Config::OUTPUT_TIMEZONE);
      if ($maxTime === Null) {
        $nowTime = new DateTime();
        $nowTime->setTimezone($outputTimezone);
        $maxTime = $nowTime;
      }
      $returnList = [];
      $entryCount = 0;
      foreach ($this->entries()->entries() as $entry) {
        if ($entry->time() >= $maxTime) {
          continue;
        }
        $returnList[] = $entry;
        $entryCount++;
        if ($limit !== Null && $entryCount >= $limit) {
          return new EntryGroup($this->dbConn, $returnList);
        }
      }
      return new EntryGroup($this->dbConn, $returnList);
    } else {
      return new EntryGroup($this->dbConn, $this->entries);
    }
  }

  public function feedEntry(BaseEntry $entry, Application $app, $nested=False) {
    // takes a feed entry from the current object and outputs feed markup for this feed entry.
    $outputTimezone = new DateTimeZone(Config::OUTPUT_TIMEZONE);
    $serverTimezone = new DateTimeZone(Config::SERVER_TIMEZONE);
    $nowTime = new DateTime("now", $outputTimezone);

    $diffInterval = $nowTime->diff($entry->time());

    $feedMessage = $entry->formatFeedEntry($app->user);

    $blankEntryComment = new Comment($this->dbConn, 0, $app->user, $entry);

    $entryType = $nested ? "div" : "li";

    $output = "      <".$entryType." class='media'>
        <div class='pull-right feedDate' data-time='".$entry->time()->format('U')."'>".ago($diffInterval)."</div>
        ".$entry->user->link("show", "<img class='feedAvatarImg' src='".joinPaths(Config::ROOT_URL, escape_output($entry->user->avatarPath))."' />", True, array('class' => 'feedAvatar pull-left'))."
        <div class='media-body feedText'>
          <div class='feedEntry'>
            <h4 class='media-heading feedUser'>".$feedMessage['title']."</h4>
            ".$feedMessage['text']."\n";
    if ($entry->allow($app->user, 'delete')) {
      $output .= "            <ul class='feedEntryMenu hidden'><li>".$entry->link("delete", "<i class='icon-trash'></i> Delete", True)."</li></ul>\n";
    }
    $output .= "          </div>\n";
    if ($entry->comments) {
      $commentGroup = new EntryGroup($app->dbConn, $entry->comments);
      $commentGroup->info();
      $commentGroup->users();
      $commentGroup->comments();
      foreach ($commentGroup->entries() as $commentEntry) {
        $output .= $this->feedEntry($commentEntry, $app, True);
      }
    }
    if ($entry->allow($app->user, 'comment') && $blankEntryComment->depth() < 2) {
      $output .= "<div class='entryComment'>".$blankEntryComment->view('inlineForm', $app, array('currentObject' => $entry))."</div>\n";
    }
    $output .= "          </div>
      </".$entryType.">\n";
    return $output;
  }

  public function feed(EntryGroup $entries, Application $app, $numEntries=50, $emptyFeedText="") {
    // takes a list of entries (given by entries()) and returns markup for the resultant feed.

    // sort by key and grab only the latest numEntries.
    $entries->comments();
    $entries = array_sort_by_method($entries->entries(), 'time', array(), 'desc');
    $entries = array_slice($entries, 0, $numEntries);
    if (!$entries) {
      $output .= $emptyFeedText;
    } else {
      // now pull info en masse for these entries.
      $entryGroup = new EntryGroup($app->dbConn, $entries);
      $entryGroup->info();
      $entryGroup->users();
      $entryGroup->anime();
      $entryGroup->comments();

      $output = "<ul class='media-list ajaxFeed' data-url='".$this->url("feed")."'>\n";
      $feedOutput = [];
      foreach ($entryGroup->entries() as $entry) {
        $feedOutput[] = $this->feedEntry($entry, $app);
      }
      $output .= implode("\n", $feedOutput);
      $output .= "</ul>\n";
    }
    return $output;
  }
}

?>