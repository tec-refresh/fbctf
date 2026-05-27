<?php declare(strict_types=1);

class GameAjaxController extends AjaxController {
  protected function getFilters(): array {
    return [
      'POST' => [
        'level_id' => FILTER_VALIDATE_INT,
        'answer' => FILTER_UNSAFE_RAW,
        'csrf_token' => FILTER_UNSAFE_RAW,
        'livesync_username' => FILTER_UNSAFE_RAW,
        'livesync_password' => FILTER_UNSAFE_RAW,
        'team_name' => FILTER_UNSAFE_RAW,
        'action' => [
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => ['regexp' => '/^[\w\-]+$/'],
        ],
        'page' => [
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => ['regexp' => '/^[\w\-]+$/'],
        ],
      ],
    ];
  }
  protected function getActions(): array {
    return ['answer_level', 'get_hint', 'open_level'];
  }
  protected function handleAction(
    string $action,
    array $params,
  ): string {
    if ($action !== 'none') {
      // CSRF check
      if (idx($params, 'csrf_token') !== SessionUtils::CSRFToken()) {
        return Utils::error_response('CSRF token is invalid', 'game');
      }
    }

    switch ($action) {
      case 'none':
        return Utils::error_response('Invalid action', 'game');
      case 'answer_level':
        $scoring = Configuration::get('scoring');
        if ($scoring->getValue() === '1') {
          $level_id = must_have_int($params, 'level_id');
          $answer = must_have_string($params, 'answer');
          list($check_base, $check_status, $check_answer) =
            [
              Level::checkBase($level_id),
              Level::checkStatus($level_id),
              Level::checkAnswer($level_id, $answer),
            ];
          // Check if level is not a base or if level isn't active
          if ($check_base || !$check_status) {
            return Utils::error_response('Failed', 'game');
            // Check if answer is valid
          } else if ($check_answer) {
            // Give points and update last score for team
            $check_answered = Level::scoreLevel($level_id, SessionUtils::sessionTeam());
            if (!$check_answered) {
              return Utils::ok_response('Double score for you! SIKE!', 'game');
            }
            return Utils::ok_response('Success', 'game');
          } else {
            FailureLog::logFailedScore(
              $level_id,
              SessionUtils::sessionTeam(),
              $answer,
            );
            return Utils::error_response('Failed', 'game');
          }
        } else {
          return Utils::error_response('Failed', 'game');
        }
      case 'get_hint':
        $requested_hint = Level::levelHint(
          must_have_int($params, 'level_id'),
          SessionUtils::sessionTeam(),
        );
        if ($requested_hint !== null) {
          MultiTeam::invalidateMCRecords('ALL_TEAMS'); // Invalidate Memcached MultiTeam data.
          MultiTeam::invalidateMCRecords('POINTS_BY_TYPE'); // Invalidate Memcached MultiTeam data.
          MultiTeam::invalidateMCRecords('LEADERBOARD'); // Invalidate Memcached MultiTeam data.
          return Utils::hint_response($requested_hint, 'OK');
        } else {
          return Utils::hint_response('', 'ERROR');
        }
      case 'open_level':
        return Utils::ok_response('Success', 'admin');
      case 'set_team_name':
        $updated_team_name = Team::setTeamName(
          SessionUtils::sessionTeam(),
          must_have_string($params, 'team_name'),
        );
        if ($updated_team_name === true) {
          return Utils::ok_response('Success', 'game');
        } else {
          return Utils::error_response('Failed', 'game');
        }
      case 'set_livesync_password':
        $livesync_password_update = Team::setLiveSyncPassword(
          SessionUtils::sessionTeam(),
          "fbctf",
          must_have_string($params, 'livesync_username'),
          must_have_string($params, 'livesync_password'),
        );
        if ($livesync_password_update === true) {
          return Utils::ok_response('Success', 'game');
        } else {
          return Utils::error_response('Failed', 'game');
        }
      default:
        return Utils::error_response('Invalid action', 'game');
    }
  }
}
