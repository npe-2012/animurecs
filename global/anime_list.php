<?php
class AnimeList extends BaseList {
  // anime list.
  public function __construct($database, $user_id=Null) {
    parent::__construct($database, $user_id);
    $this->modelTable = "anime_lists";
    $this->modelPlural = "animeLists";
    $this->partName = "episode";
    $this->listType = "Anime";
    $this->typeVerb = "watching";
    $this->listTypeLower = strtolower($this->listType);
    $this->typeID = $this->listTypeLower.'_id';
  }
  public function allow($authingUser, $action) {
    // takes a user object and an action and returns a bool.
    switch($action) {
      case 'new':
      case 'edit':
      case 'delete':
        if ($authingUser->id == $this->user_id || ($authingUser->isModerator() || $authingUser->isAdmin()) ) {
          return True;
        }
        return False;
        break;
      case 'index':
        if ($authingUser->isAdmin()) {
          return True;
        }
        return False;
        break;
      default:
      case 'show':
        return True;
        break;
    }
  }
  public function entryLink($id, $action="show", $text=Null, $raw=False) {
    // returns an HTML link to an entry link, with text provided.
    $text = ($text === Null) ? "List" : $text;
    return "<a href='/anime_lists/".intval($id)."/".urlencode($action)."/?user_id=".intval($this->user_id)."'>".($raw ? $text : escape_output($text))."</a>";
  }
}
?>