<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class CountryDataController extends DataController {
  public function generateData(): void {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    $my_team = MultiTeam::team(SessionUtils::sessionTeam());
    $gameboard = Configuration::get('gameboard');
    $all_active_levels = Level::allActiveLevels();

    $countries_data = (object) [];

    // If gameboard refresing is disabled, exit
    if ($gameboard->getValue() === '0') {
      $this->jsonSend($countries_data);
      exit(1);
    }

    foreach ($all_active_levels as $level) {
      $country = Country::get(intval($level->getEntityId()));
      $category = Category::singleCategory($level->getCategoryId());
      $attachments_list = Attachment::allAttachmentsFileNamesLinks(
        $level->getId(),
      );
      $links_list = Link::allLinksValues($level->getId());
      $completed_by = MultiTeam::completedLevelTeamNames(
        $level->getId(),
      );

      if (!($country instanceof Country)) {
        throw new RuntimeException('country should be of type Country');
      }
      if (!($category instanceof Category)) {
        throw new RuntimeException('category should be of type Category');
      }

      if (!$country) {
        continue;
      }

      if ($level->getHint() !== '') {
        // There is hint, can this team afford it?
        if ($level->getPenalty() > $my_team->getPoints()) { // Not enough points
          $hint_cost = -2;
          $hint = 'no';
        } else {
          $hint = HintLog::previousHint(
            $level->getId(),
            $my_team->getId(),
            false,
          );
          $score = ScoreLog::previousScore(
            $level->getId(),
            $my_team->getId(),
            false,
          );

          // Has this team requested this hint or scored this level before?
          if ($hint || $score) {
            $hint_cost = 0;
          } else {
            $hint_cost = $level->getPenalty();
          }
          $hint = ($hint_cost === 0) ? $level->getHint() : 'yes';
        }
      } else { // No hints
        $hint_cost = -1;
        $hint = 'no';
      }

      // Who is the first owner of this level
      if ($completed_by) {
        $owner = MultiTeam::firstCapture($level->getId());
        $owner = $owner->getName();
      } else {
        $owner = 'Uncaptured';
      }
      $country_data = (object) [
        'level_id' => $level->getId(),
        'title' => $level->getTitle(),
        'intro' => $level->getDescription(),
        'type' => $level->getType(),
        'points' => $level->getPoints(),
        'bonus' => $level->getBonus(),
        'category' => $category->getCategory(),
        'owner' => $owner,
        'completed' => $completed_by,
        'hint' => $hint,
        'hint_cost' => $hint_cost,
        'attachments' => $attachments_list,
        'links' => $links_list,
      ];
      $countries_data->{$country->getName()} = $country_data;
    }

    $this->jsonSend($countries_data);
  }
}

$countryData = new CountryDataController();
$countryData->sendData();
