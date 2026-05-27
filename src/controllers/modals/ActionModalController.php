<?php declare(strict_types=1);

class ActionModalController extends ModalController {
  private function getModal(string $modal): array {
    switch ($modal) {
      case 'begin-game':
        $title =
          '<h4>' . htmlspecialchars(tr('begin_')) . '<span class="highlighted">' . htmlspecialchars(tr('Game')) . '</span>' . '</h4>';
        $content =
          '<div class="action-main">' . '<p>' . htmlspecialchars(tr(
                'Are you sure you want to kick off the game? Logs will be cleared and progressive scoreboard will start',
              )) . '</p>' . '<div class="action-actionable">' . '<a href="#" class="fb-cta cta--red js-close-modal">' . htmlspecialchars(tr('No')) . '</a>' . '<a href="#" id="begin_game" class="fb-cta cta--yellow">' . htmlspecialchars(tr('Yes')) . '</a>' . '</div>' . '</div>';
        return [$title, $content];
      case 'end-game':
        $title =
          '<h4>' . htmlspecialchars(tr('end_')) . '<span class="highlighted">' . htmlspecialchars(tr('Game')) . '</span>' . '</h4>';
        $content =
          '<div class="action-main">' . '<p>' . htmlspecialchars(tr('Are you sure you want to finish the current game?')) . '</p>' . '<div class="action-actionable">' . '<a href="#" class="fb-cta cta--red js-close-modal">' . htmlspecialchars(tr('No')) . '</a>' . '<a href="#" id="end_game" class="fb-cta cta--yellow">' . htmlspecialchars(tr('Yes')) . '</a>' . '</div>' . '</div>';
        return [$title, $content];
      case 'pause-game':
        $title =
          '<h4>' . htmlspecialchars(tr('pause_')) . '<span class="highlighted">' . htmlspecialchars(tr('Game')) . '</span>' . '</h4>';
        $content =
          '<div class="action-main">' . '<p>' . htmlspecialchars(tr('Are you sure you want to pause the current game?')) . '</p>' . '<div class="action-actionable">' . '<a href="#" class="fb-cta cta--red js-close-modal">' . htmlspecialchars(tr('No')) . '</a>' . '<a href="#" id="pause_game" class="fb-cta cta--yellow">' . htmlspecialchars(tr('Yes')) . '</a>' . '</div>' . '</div>';
        return [$title, $content];
      case 'unpause-game':
        $title =
          '<h4>' . htmlspecialchars(tr('unpause_')) . '<span class="highlighted">' . htmlspecialchars(tr('Game')) . '</span>' . '</h4>';
        $content =
          '<div class="action-main">' . '<p>' . htmlspecialchars(tr('Are you sure you want to unpause the current game?')) . '</p>' . '<div class="action-actionable">' . '<a href="#" class="fb-cta cta--red js-close-modal">' . htmlspecialchars(tr('No')) . '</a>' . '<a href="#" id="unpause_game" class="fb-cta cta--yellow">' . htmlspecialchars(tr('Yes')) . '</a>' . '</div>' . '</div>';
        return [$title, $content];
      case 'delete-team':
        $title =
          '<h4>' . htmlspecialchars(tr('delete_')) . '<span class="highlighted">' . htmlspecialchars(tr('Team')) . '</span>' . '</h4>';
        $content =
          '<div class="action-main">' . '<p>' . htmlspecialchars(tr(
                'Are you sure you want to delete this team? All data for this team will be irreversibly removed, including scoring logs. If you prefer to retain data, you can disable the team instead.',
              )) . '</p>' . '<div class="action-actionable">' . '<a href="#" class="fb-cta cta--red js-close-modal">' . htmlspecialchars(tr('No')) . '</a>' . '<a href="#" id="delete_team" class="fb-cta cta--yellow">' . htmlspecialchars(tr('Yes')) . '</a>' . '</div>' . '</div>';
        return [$title, $content];
      case 'delete-level':
        $title =
          '<h4>' . htmlspecialchars(tr('delete_')) . '<span class="highlighted">' . htmlspecialchars(tr('Level')) . '</span>' . '</h4>';
        $content =
          '<div class="action-main">' . '<p>' . htmlspecialchars(tr(
                'Are you sure you want to delete this level? All data for this level will be irreversibly removed, including scores.',
              )) . '</p>' . '<div class="action-actionable">' . '<a href="#" class="fb-cta cta--red js-close-modal">' . htmlspecialchars(tr('No')) . '</a>' . '<a href="#" id="delete_level" class="fb-cta cta--yellow">' . htmlspecialchars(tr('Yes')) . '</a>' . '</div>' . '</div>';
        return [$title, $content];
      case 'logout':
        $title =
          '<h4>' . htmlspecialchars(tr('status_')) . '<span class="highlighted">' . htmlspecialchars(tr('Logout')) . '</span>' . '</h4>';
        $content =
          '<div class="action-main">' . '<p>' . htmlspecialchars(tr('Are you sure you want to logout from the game?')) . '</p>' . '<div class="action-actionable">' . '<a href="#" class="fb-cta cta--red js-close-modal">' . htmlspecialchars(tr('No')) . '</a>' . '<a href="index.php?p=logout" class="fb-cta cta--yellow">' . htmlspecialchars(tr('Yes')) . '</a>' . '</div>' . '</div>';
        return [$title, $content];
      case 'save':
        $title =
          '<h4>' . htmlspecialchars(tr('status_')) . '<span class="highlighted">' . htmlspecialchars(tr('Saved')) . '</span>' . '</h4>';
        $content =
          '<div class="action-main">' . '<p>' . htmlspecialchars(tr('All changes have been successfully saved.')) . '</p>' . '<div class="action-actionable">' . '<a href="#" class="fb-cta cta--yellow js-close-modal js-confirm-save">' . htmlspecialchars(tr('OK')) . '</a>' . '</div>' . '</div>';
        return [$title, $content];
      case 'error':
        $title =
          '<h4>' . htmlspecialchars(tr('status_')) . '<span class="highlighted--red">' . htmlspecialchars(tr('Error')) . '</span>' . '</h4>';
        $content =
          '<div class="action-main">' . '<div class="error-text">' . '<p>' . htmlspecialchars(tr(
                  'Sorry your form was not saved. Please correct the all errors and save again.',
                )) . '</p>' . '</div>' . '<ul class="errors-list">' . '</ul>' . '<div class="action-actionable">' . '<a href="#" class="fb-cta cta--yellow js-close-modal">' . htmlspecialchars(tr('OK')) . '</a>' . '</div>' . '</div>';
        return [$title, $content];
      case 'cancel':
        $title =
          '<h4>' . htmlspecialchars(tr('cancel_')) . '<span class="admin-section-name highlighted">' . '</span>' . '</h4>';
        $content =
          '<div class="action-main">' . '<p>' . htmlspecialchars(tr(
                'Are you sure you want to cancel? You have unsaved changes that will be reverted.',
              )) . '</p>' . '<div class="action-actionable">' . '<a href="#" class="fb-cta cta--red js-close-modal">' . htmlspecialchars(tr('No')) . '</a>' . '<a href="#" class="fb-cta cta--yellow js-close-modal">' . htmlspecialchars(tr('Yes')) . '</a>' . '</div>' . '</div>';
        return [$title, $content];
      case 'import-done':
        $title =
          '<h4>' . htmlspecialchars(tr('status_')) . '<span class="highlighted">' . htmlspecialchars(tr('Imported')) . '</span>' . '</h4>';
        $content =
          '<div class="action-main">' . '<p>' . htmlspecialchars(tr('Items have been imported successfully')) . '</p>' . '<div class="action-actionable">' . '<a href="#" class="fb-cta cta--yellow js-close-modal">' . htmlspecialchars(tr('OK')) . '</a>' . '</div>' . '</div>';
        return [$title, $content];
      case 'restore-database':
        $title =
          '<h4>' . htmlspecialchars(tr('restore_')) . '<span class="highlighted">' . htmlspecialchars(tr('Database')) . '</span>' . '</h4>';
        $content =
          '<div class="action-main">' . '<p>' . htmlspecialchars(tr(
                'Are you sure you want to restore the database? This will overwrite ALL existing data!',
              )) . '</p>' . '<div class="action-actionable">' . '<a href="#" class="fb-cta cta--red js-close-modal">' . htmlspecialchars(tr('No')) . '</a>' . '<a href="#" id="restore_database" class="fb-cta cta--yellow">' . htmlspecialchars(tr('Yes')) . '</a>' . '</div>' . '</div>';
        return [$title, $content];
      case 'reset-database':
        $title =
          '<h4>' . htmlspecialchars(tr('reset_')) . '<span class="highlighted">' . htmlspecialchars(tr('Database')) . '</span>' . '</h4>';
        $content =
          '<div class="action-main">' . '<p>' . htmlspecialchars(tr(
                'Are you sure you want to reset the database? This will destroy ALL data! Admin accounts will remain.',
              )) . '</p>' . '<div class="action-actionable">' . '<a href="#" class="fb-cta cta--red js-close-modal">' . htmlspecialchars(tr('No')) . '</a>' . '<a href="#" id="reset_database" class="fb-cta cta--yellow">' . htmlspecialchars(tr('Yes')) . '</a>' . '</div>' . '</div>';
        return [$title, $content];
      case 'account':
        $title =
          '<h4>' . htmlspecialchars(tr('account_')) . '<span class="highlighted">' . htmlspecialchars(tr('Settings')) . '</span>' . '</h4>';
        $oauth_header = '';
        if (Configuration::getFacebookOAuthSettingsExists() === true) {
          $linked =
            Team::teamOAuthTokenExists(
              'facebook_oauth',
              SessionUtils::sessionTeam(),
            );
          $button_text = tr('Facebook');
          $button =
            '<a name="facebook-oauth-button" href="#" class="fb-cta cta--yellow js-trigger-facebook-oauth">' . htmlspecialchars(tr('Link Your')) . '<br />' . htmlspecialchars(tr($button_text)) . '<br />' . htmlspecialchars(tr('Account')) . '</a>';
          if ($linked === true) {
            $button =
              '<a name="facebook-oauth-button" href="#" class="fb-cta cta--yellowe">' . htmlspecialchars(tr($button_text)) . '<br />' . htmlspecialchars(tr('Account Is Linked')) . '</a>';
          }
          $oauth_header =
            '<p>' . htmlspecialchars(tr(
                'You may link your FBCTF account on this instance with your other providers.  Note that this will provide your email address to the administrators of this FBCTF instance.',
              )) . '</p>';
          $facebook_oauth_content =
            '<div class="facebook-link-form">' . '<div class="action-actionable">' . htmlspecialchars($button) . '</div>' . '<br />' . '<span class="facebook-link-response highlighted--blue">' . '</span>' . '<br />' . '</div>';
        } else {
          $facebook_oauth_content = '';
        }
        if (Configuration::getGoogleOAuthFileExists() === true) {
          $linked =
            Team::teamOAuthTokenExists(
              'google_oauth',
              SessionUtils::sessionTeam(),
            );
          $button_text = tr('Google');
          $button =
            '<a name="google-oauth-button" href="#" class="fb-cta cta--yellow js-trigger-google-oauth">' . htmlspecialchars(tr('Link Your')) . '<br />' . htmlspecialchars(tr($button_text)) . '<br />' . htmlspecialchars(tr('Account')) . '</a>';
          if ($linked === true) {
            $button =
              '<a name="google-oauth-button" href="#" class="fb-cta cta--yellowe">' . htmlspecialchars(tr($button_text)) . '<br />' . htmlspecialchars(tr('Account Is Linked')) . '</a>';
          }
          $oauth_header =
            '<p>' . htmlspecialchars(tr(
                'You may link your FBCTF account on this instance with your other providers.  Note that this will provide your email address to the administrators of this FBCTF instance.',
              )) . '</p>';
          $google_oauth_content =
            '<div class="google-link-form">' . '<div class="action-actionable">' . htmlspecialchars($button) . '</div>' . '<br />' . '<span class="google-link-response highlighted--blue">' . '</span>' . '<br />' . '</div>';
        } else {
          $google_oauth_content = '';
        }

        $team =
          MultiTeam::team(SessionUtils::sessionTeam());
        $team_name = $team->getName();

        $content =
          '<div class="action-main">' . htmlspecialchars(tr('Change your team name.')) . '<form class="fb-form-no-padding team-name-form">' . '<input name="set_team_name" type="hidden" value="" />' . '<div class="form-el el--text">' . '<input placeholder="' . htmlspecialchars(tr('Set your team name')) . '" name="team_name" type="text" value="' . htmlspecialchars($team_name) . '" autocomplete="off" />' . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(SessionUtils::CSRFToken()) . '" />' . '</div>' . '<div class="action-actionable">' . '<a class="fb-cta cta--yellow js-trigger-account-team-name-save">' . htmlspecialchars(tr('Update')) . '</a>' . '</div>' . '<br />' . '<span class="team-name-form-response highlighted--blue">' . '</span>' . '</form>' . $oauth_header . '<div class="fb-column-container">' . '<div class="col col-pad col-1-2">' . $facebook_oauth_content . '</div>' . '<div class="col col-pad col-2-2">' . $google_oauth_content . '</div>' . '</div>' . '<p>' . htmlspecialchars(tr(
                'Setup your FBCTF Live Sync credentials.  These credentials must be the SAME on all other FBCTF instances that you are linking.  DO NOT use your account password.',
              )) . '</p>' . '<br />' . '<form class="fb-form-no-padding account-link-form">' . '<input name="set_livesync_password" type="hidden" value="" />' . '<div class="form-el el--text">' . '<input placeholder="' . htmlspecialchars(tr('Set your live sync username')) . '" name="livesync_username" type="text" autocomplete="off" />' . '<input placeholder="' . htmlspecialchars(tr('Set your live sync password')) . '" name="livesync_password" type="password" autocomplete="off" />' . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(SessionUtils::CSRFToken()) . '" />' . '</div>' . '<div class="action-actionable">' . '<a class="fb-cta cta--yellow js-trigger-account-save">' . htmlspecialchars(tr('Submit')) . '</a>' . '</div>' . '<span class="account-link-form-response highlighted--blue">' . '</span>' . '</form>' . '<div class="action-actionable">' . '<a href="#" class="fb-cta cta--red js-close-modal">' . htmlspecialchars(tr('Close')) . '</a>' . '</div>' . '</div>';
        return [$title, $content];
      default:
        if (!(false)) { throw new \RuntimeException('Invalid modal name %s', strval($modal)); }
    }
  }
  public function render(string $modal): string {
    list($title, $content) = $this->getModal($modal);

    return
      '<div class="fb-modal-content">' . '<header class="modal-title">' . $title . '<a href="#" class="js-close-modal">' . '<svg class="icon icon--close">' . '<use href="#icon--close" />' . '</svg>' . '</a>' . '</header>' . $content . '</div>';
  }
}
