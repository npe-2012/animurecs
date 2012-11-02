<?php
class BaseList extends BaseObject {
  public $user_id;

  protected $startTime, $endTime;
  protected $uniqueListAvg, $uniqueListStdDev, $entryAvg, $entryStdDev;
  protected $statusStrings, $scoreStrings, $partStrings;

  protected $entries, $uniqueList, $partName, $typeVerb;

  public function __construct($database, $user_id=Null) {
    parent::__construct($database, $user_id);
    $this->modelTable = "";
    $this->modelPlural = "";
    $this->partName = "";
    $this->listType = "";
    $this->typeVerb = "";
    $this->listTypeLower = strtolower($this->listType);
    $this->typeID = $this->listTypeLower.'_id';
    // strings with which to build feed messages.
    // the status messages we build will be different depending on 1) whether or not this is the first entry, and 2) what the status actually is.
    $this->statusStrings = array(0 => array(0 => "did something mysterious with [TITLE]",
                                      1 => "is now [TYPE_VERB] [TITLE]",
                                      2 => "marked [TITLE] as completed",
                                      3 => "marked [TITLE] as on-hold",
                                      4 => "marked [TITLE] as dropped",
                                      6 => "plans to watch [TITLE]"),
                                  1 => array(0 => "removed [TITLE]",
                                            1 => "started [TYPE_VERB] [TITLE]",
                                            2 => "finished [TITLE]",
                                            3 => "put [TITLE] on hold",
                                            4 => "dropped [TITLE]",
                                            6 => "now plans to watch [TITLE]"));
    $this->scoreStrings = array(0 => array("rated [TITLE] a [SCORE]/10", "and rated it a [SCORE]/10"),
                          1 => array("unrated [TITLE]", "and unrated it"));
    $this->partStrings = array("just finished [PART_NAME] [PART]/[TOTAL_PARTS] of [TITLE]", "and finished [PART_NAME] [PART]/[TOTAL_PARTS]");
    $this->uniqueListAvg = $this->uniqueListStdDev = $this->entryAvg = $this->entryStdDev = 0;
    if ($user_id === 0) {
      $this->user_id = 0;
      $this->username = $this->startTime = $this->endTime = "";
      $this->entries = $this->uniqueList = [];
    } else {
      $this->user_id = intval($user_id);
      $this->entries = $this->uniqueList = Null;
    }
  }
  public function create_or_update($entry) {
    /*
      Creates or updates an existing list entry for the current user.
      Takes an array of entry parameters.
      Returns the resultant list entry ID.
    */
    $params = [];
    foreach ($entry as $parameter => $value) {
      if (!is_array($value)) {
        if (is_numeric($value)) {
            $params[] = "`".$this->dbConn->real_escape_string($parameter)."` = ".intval($value);
        } else {
          $params[] = "`".$this->dbConn->real_escape_string($parameter)."` = ".$this->dbConn->quoteSmart($value);
        }
      }
    }

    try {
      $user = new User($this->dbConn, intval($entry['user_id']));
      $type = new $this->listType($this->dbConn, intval($entry[$this->typeID]));
    } catch (Exception $e) {
      return False;
    }

    // check to see if this is an update.
    if (isset($this->entries()[intval($entry['id'])])) {
      $updateDependency = $this->dbConn->stdQuery("UPDATE `".$this->modelTable."` SET ".implode(", ", $params)." WHERE `id` = ".intval($entry['id'])." LIMIT 1");
      if (!$updateDependency) {
        return False;
      }
      // update list locally.
      if ($this->uniqueList()[intval($entry[$this->typeID])]['score'] != intval($entry['score']) || $this->uniqueList()[intval($entry[$this->typeID])]['status'] != intval($entry['status']) || $this->uniqueList()[intval($entry[$this->typeID])][$this->partName] != intval($entry[$this->partName])) {
        if (intval($entry['status']) == 0) {
          unset($this->uniqueList[intval($entry[$this->typeID])]);
        } else {
          $this->uniqueList[intval($entry[$this->typeID])] = array($this->typeID => intval($entry[$this->typeID]), 'time' => $entry['time'], 'score' => intval($entry['score']), 'status' => intval($entry['status']), $this->partName => intval($entry[$this->partName]));
        }
      }
      $returnValue = intval($entry['id']);
    } else {
      $timeString = (isset($entry['time']) ? "" : ", `time` = NOW()");
      $insertDependency = $this->dbConn->stdQuery("INSERT INTO `".$this->modelTable."` SET ".implode(",", $params).$timeString);
      if (!$insertDependency) {
        return False;
      }
      $returnValue = intval($this->dbConn->insert_id);
      // insert list locally.
      $this->uniqueList();
      if (intval($entry['status']) == 0) {
        unset($this->uniqueList[intval($entry[$this->typeID])]);
      } else {
        $this->uniqueList[intval($entry[$this->typeID])] = array($this->typeID => intval($entry[$this->typeID]), 'time' => $entry['time'], 'score' => intval($entry['score']), 'status' => intval($entry['status']), $this->partName => intval($entry[$this->partName]));
      }
    }
    $this->entries[intval($returnValue)] = $entry;
    return $returnValue;
  }
  public function delete($entries=False) {
    /*
      Deletes list entries.
      Takes an array of entry ids as input, defaulting to all entries.
      Returns a boolean.
    */
    if ($entries === False) {
      $entries = array_keys($this->entries());
    }
    if (is_numeric($entries)) {
      $entries = [intval($entries)];
    }
    $entryIDs = array();
    foreach ($entries as $entry) {
      if (is_numeric($entry)) {
        $entryIDs[] = intval($entry);
      }
    }
    if (count($entryIDs) > 0) {
      $drop_entries = $this->dbConn->stdQuery("DELETE FROM `".$this->modelTable."` WHERE `user_id` = ".intval($this->user_id)." AND `id` IN (".implode(",", $entryIDs).") LIMIT ".count($entryIDs));
      if (!$drop_entries) {
        return False;
      }
    }
    foreach ($entryIDs as $entryID) {
      unset($this->entries[intval($entryID)]);
    }
    return True;
  }
  public function getInfo() {
    $userInfo = $this->dbConn->queryFirstRow("SELECT `user_id`, MIN(`time`) AS `start_time`, MAX(`time`) AS `end_time` FROM `".$this->modelTable."` WHERE `user_id` = ".intval($this->user_id));
    if (!$userInfo) {
      return False;
    }
    $this->startTime = intval($userInfo['start_time']);
    $this->endTime = intval($userInfo['end_time']);
  }
  public function startTime() {
    return $this->returnInfo("startTime");
  }
  public function endTime() {
    return $this->returnInfo("endTime");
  }
  public function getEntries() {
    // retrieves a list of arrays corresponding to anime list entries belonging to this user.
    $returnList = $this->dbConn->queryAssoc("SELECT `id`, `".$this->typeID."`, `time`, `status`, `score`, `".$this->partName."` FROM `".$this->modelTable."` WHERE `user_id` = ".intval($this->user_id)." ORDER BY `time` DESC", "id");
    $this->entryAvg = $this->entryStdDev = $entrySum = 0;
    $entryCount = count($returnList);
    foreach ($returnList as $key=>$entry) {
      $returnList[$key][$this->listTypeLower] = new $this->listType($this->dbConn, intval($entry[$this->typeID]));
      unset($returnList[$key][$this->typeID]);
      $entrySum += intval($entry['score']);
    }
    $this->entryAvg = ($entryCount === 0) ? 0 : $entrySum / $entryCount;
    $entrySum = 0;
    if ($entryCount > 1) {
      foreach ($returnList as $entry) {
        $entrySum += pow(intval($entry['score']) - $this->entryAvg, 2);
      }
      $this->entryStdDev = pow($entrySum / ($entryCount - 1), 0.5);
    }
    return $returnList;
  }
  public function entries($maxTime=Null, $limit=Null) {
    if ($this->entries === Null) {
      $this->entries = $this->getEntries();
    }
    if ($maxTime !== Null || $limit !== Null) {
      // Returns a list of up to $limit entries up to $maxTime.
      $serverTimezone = new DateTimeZone(SERVER_TIMEZONE);
      $outputTimezone = new DateTimeZone(OUTPUT_TIMEZONE);
      if ($maxTime === Null) {
        $nowTime = new DateTime();
        $nowTime->setTimezone($outputTimezone);
        $maxTime = $nowTime;
      }
      $returnList = [];
      $entryCount = 0;
      foreach ($this->entries() as $entry) {
        $entryDate = new DateTime($value['time'], $serverTimezone);
        if ($entryDate > $maxTime) {
          continue;
        }
        $entry['user_id'] = intval($this->user_id);
        $returnList[] = $entry;
        $entryCount++;
        if ($limit !== Null && $entryCount >= $limit) {
          return $returnList;
        }
      }
      return $returnList;
    } else {
      return $this->entries;
    }
  }
  public function getUniqueList() {
    // retrieves a list of $this->typeID, time, status, score, $this->partName arrays corresponding to the latest list entry for each thing the user has consumed.
    $returnList = $this->dbConn->queryAssoc("SELECT `".$this->modelTable."`.`id`, `".$this->typeID."`, `time`, `score`, `status`, `".$this->partName."` FROM (
                                              SELECT MAX(`id`) AS `id` FROM `".$this->modelTable."`
                                              WHERE `user_id` = ".intval($this->user_id)."
                                              GROUP BY `".$this->typeID."`
                                            ) `p` INNER JOIN `".$this->modelTable."` ON `".$this->modelTable."`.`id` = `p`.`id`
                                            WHERE `status` != 0
                                            ORDER BY `status` ASC, `score` DESC", $this->typeID);

    $this->uniqueListAvg = $this->uniqueListStdDev = $uniqueListSum = $uniqueListCount = 0;
    foreach ($returnList as $key=>$entry) {
      $returnList[$key][$this->listTypeLower] = new $this->listType($this->dbConn, intval($entry[$this->typeID]));
      unset($returnList[$key][$this->typeID]);
      if ($entry['score'] != 0) {
        $uniqueListCount++;
        $uniqueListSum += intval($entry['score']);
      }
    }
    $this->uniqueListAvg = ($uniqueListCount === 0) ? 0 : $uniqueListSum / $uniqueListCount;
    $uniqueListSum = 0;
    if ($uniqueListCount > 1) {
      foreach ($returnList as $entry) {
        if ($entry['score'] != 0) {
          $uniqueListSum += pow(intval($entry['score']) - $this->uniqueListAvg, 2);
        }
      }
      $this->uniqueListStdDev = pow($uniqueListSum / ($uniqueListCount - 1), 0.5);
    }
    return $returnList;
  }
  public function uniqueList() {
    if ($this->uniqueList === Null) {
      $this->uniqueList = $this->getUniqueList();
    }
    return $this->uniqueList;
  }
  public function listSection($status=Null, $score=Null) {
    // returns a section of this user's unique list.
    return array_filter($this->uniqueList(), function($value) use ($status, $score) {
      return (($status !== Null && intval($value['status']) === $status) || ($score !== Null && intval($value['score']) === $score));
    });
  }
  public function prevEntry($id, $beforeTime) {
    // Returns the previous entry in this user's entry list for $this->typeID and before $beforeTime.
    $prevEntry = array('status' => 0, 'score' => 0, $this->partName => 0);
    $serverTimezone = new DateTimeZone(SERVER_TIMEZONE);
    foreach ($this->entries as $entry) {
      $entryDate = new DateTime($entry['time'], $serverTimezone);
      if ($entryDate >= $beforeTime) {
        continue;
      }
      if ($entry[$this->listTypeLower]->id == $id) {
        return $entry;
      }
    }
    return $prevEntry;
  }
  public function feedEntry($entry, $user, $currentUser) {
    // fetch the previous feed entry and compare values against current entry.

    $outputTimezone = new DateTimeZone(OUTPUT_TIMEZONE);
    $serverTimezone = new DateTimeZone(SERVER_TIMEZONE);
    $nowTime = new DateTime("now", $outputTimezone);

    $entryTime = new DateTime($entry['time'], $serverTimezone);
    $diffInterval = $nowTime->diff($entryTime);
    $prevEntry = $this->prevEntry($entry[$this->listTypeLower]->id, $entryTime);

    $statusChanged = (bool) ($entry['status'] != $prevEntry['status']);
    $scoreChanged = (bool) ($entry['score'] != $prevEntry['score']);
    $partChanged = (bool) ($entry[$this->partName] != $prevEntry[$this->partName]);
    
    // concatenate appropriate parts of this status text.
    $statusTexts = [];
    if ($statusChanged) {
      $statusTexts[] = $this->statusStrings[intval((bool)$prevEntry)][intval($entry['status'])];
    }
    if ($scoreChanged) {
      $statusTexts[] = $this->scoreStrings[intval($entry['score'] == 0)][intval($statusChanged)];
    }
    if ($partChanged) {
      $statusTexts[] = $this->partStrings[intval($statusChanged || $scoreChanged)];
    }
    $statusText = implode(" ", $statusTexts);

    // replace placeholders.
    $statusText = str_replace("[TYPE_VERB]", $this->typeVerb, $statusText);
    $statusText = str_replace("[PART_NAME]", $this->partName, $statusText);
    $statusText = str_replace("[TITLE]", $entry[$this->listTypeLower]->link("show", $entry[$this->listTypeLower]->title), $statusText);
    $statusText = str_replace("[SCORE]", $entry['score'], $statusText);
    $statusText = str_replace("[PART]", $entry[$this->partName], $statusText);
    $statusText = str_replace("[TOTAL_PARTS]", $entry[$this->listTypeLower]->{$this->partName."Count"}, $statusText);
    $statusText = ucfirst($statusText);

    $output = "";
    if ($statusText != '') {
      $output .= "  <li class='feedEntry row-fluid'>
        <div class='feedDate' data-time='".$entryTime->format('U')."'>".ago($diffInterval)."</div>
        <div class='feedAvatar'>".$user->link("show", "<img class='feedAvatarImg' src='".escape_output($user->avatarPath)."' />", True)."</div>
        <div class='feedText'>
          <div class='feedUser'>".$user->link("show", $user->username)."</div>
          ".$statusText.".\n";
      if ($currentUser->id === $user->id) {
        $output .= "            <ul class='feedEntryMenu hidden'><li>".$this->entryLink(intval($entry['id']), "delete", "<i class='icon-trash'></i> Delete", True)."</li></ul>";
      }
      $output .= "          </div>
      </li>\n";
    }
    return $output;
  }
  public function link($action="show", $text=Null, $raw=False) {
    // returns an HTML link to the current tag's profile, with text provided.
    $text = ($text === Null) ? "List" : $text;
    return "<a href='/user.php?action=".urlencode($action)."&id=".intval($this->user_id)."#".$this->listType."List'>".($raw ? $text : escape_output($text))."</a>";
  }
}
?>