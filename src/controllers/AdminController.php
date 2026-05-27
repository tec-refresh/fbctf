<?php declare(strict_types=1);

class AdminController extends Controller {
  protected function getTitle(): string {
    $custom_org = Configuration::get('custom_org');
    return tr($custom_org->getValue()).' '.tr('CTF').' | '.tr('Admin');
  }
  protected function getFilters(): array {
    return [
      'GET' => [
        'page' => [
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => ['regexp' => '/^[\w-]+$/'],
        ],
      ],
    ];
  }
  protected function getPages(): array {
    return [
      'main',
      'configuration',
      'controls',
      'announcements',
      'quiz',
      'flags',
      'bases',
      'categories',
      'countries',
      'teams',
      'logos',
      'sessions',
      'scoreboard',
      'logs',
    ];
  }

  private function generateCountriesSelect(
    int $selected,
  ): string {
    $select =
      '<select class="not_configuration" name="entity_id" disabled >';

    if ($selected === 0) {
      $select .= '<option value="0" selected>' . htmlspecialchars(tr('Auto')) . '</option>';
    } else {
      $country = Country::get(intval($selected));
      $select .= '<option value="' . htmlspecialchars(strval($country->getId())) . '" selected>' . htmlspecialchars($country->getName()) . '</option>';
    }

    $countries = Country::allAvailableCountries();
    foreach ($countries as $country) {
      $select .= '<option value="' . htmlspecialchars(strval($country->getId())) . '">' . htmlspecialchars($country->getName()) . '</option>';
    }

    $select .= '</select>';


    return $select;
  }

  private function generateLevelCategoriesSelect(
    int $selected,
  ): string {
    $categories = Category::allCategories();
    $select =
      '<select class="not_configuration" name="category_id" disabled >';

    foreach ($categories as $category) {
      if ($category->getCategory() === 'Quiz') {
        continue;
      }

      if ($category->getId() === $selected) {
        $select .= '<option id="category_option" value="' . htmlspecialchars(strval($category->getId())) . '" selected>' . htmlspecialchars($category->getCategory()) . '</option>';
      } else {
        $select .= '<option id="category_option" value="' . htmlspecialchars(strval($category->getId())) . '">' . htmlspecialchars($category->getCategory()) . '</option>';
      }
    }

    $select .= '</select>';


    return $select;
  }

  private function generateFilterCategoriesSelect(): string {
    $categories = Category::allCategories();
    $select = '<select class="not_configuration" name="category_filter" >';

    $select .= '<option class="filter_option" value="all" selected>' . htmlspecialchars(tr('All Categories')) . '</option>';
    foreach ($categories as $category) {
      if ($category->getCategory() === 'Quiz') {
        continue;
      }
      $select .= '<option class="filter_option" value="' . htmlspecialchars($category->getCategory()) . '">' . htmlspecialchars($category->getCategory()) . '</option>';
    }

    $select .= '</select>';


    return $select;
  }

  private function registrationTypeSelect(): string {
    $config = Configuration::get('registration_type');
    $type = $config->getValue();
    $select = '<select name="fb--conf--registration_type">' . '</select>';
    $select .= '<option class="fb--conf--registration_type" value="1" ' . (($type === '1') ? ' selected' : '') . '>' . htmlspecialchars(tr('Open')) . '</option>';
    $select .= '<option class="fb--conf--registration_type" value="2" ' . (($type === '2') ? ' selected' : '') . '>' . htmlspecialchars(tr('Tokenized')) . '</option>';

    return $select;
  }

  // TODO: Translate password types
  private function strongPasswordsSelect(): string {
    list($types, $config) = [
      Configuration::allPasswordTypes(),
      Configuration::currentPasswordType(),
    ];
    $select = '<select name="fb--conf--password_type">' . '</select>';
    foreach ($types as $type) {
      $select .= '<option class="fb--conf--password_type" value="' . htmlspecialchars(strval($type->getField())) . '" ' . (($type->getField() === $config->getField()) ? ' selected' : '') . '>' . htmlspecialchars($type->getDescription()) . '</option>';
    }

    return $select;
  }

  private function configurationDurationSelect(): string {
    list($config_duration_unit, $config_duration_value) = [
      Configuration::get('game_duration_unit'),
      Configuration::get('game_duration_value'),
    ];
    $duration_unit = $config_duration_unit->getValue();
    $duration_value = $config_duration_value->getValue();

    $minute_selected = $duration_unit === 'm';
    $hour_selected = $duration_unit === 'h';
    $day_selected = $duration_unit === 'd';

    return
      '<div class="fb-column-container">' . '<div class="col col-1-2">' . '<input type="number" value="' . htmlspecialchars($duration_value) . '" name="fb--conf--game_duration_value" />' . '</div>' . '<div class="col col-2-2">' . '<select name="fb--conf--game_duration_unit">' . '<option class="fb--conf--game_duration" value="m" ' . ($minute_selected ? ' selected' : '') . '>' . 'Minutes' . '</option>' . '<option class="fb--conf--game_duration" value="h" ' . ($hour_selected ? ' selected' : '') . '>' . 'Hours' . '</option>' . '<option class="fb--conf--game_duration" value="d" ' . ($day_selected ? ' selected' : '') . '>' . 'Days' . '</option>' . '</select>' . '</div>' . '</div>';
  }

  private function languageSelect(): string {
    $config = Configuration::get('language');
    $current_lang = $config->getValue();
    $available_languages = scandir('language/');
    $select = '<select name="fb--conf--language">' . '</select>';
    foreach ($available_languages as $file_name) {
      $matches = [];
      if (preg_match('/^lang_(.*)\.php$/', $file_name, $matches)) {
        $lang = $matches[1];
        $lang_name =
          locale_get_display_language($lang, $current_lang).
          " / ".
          locale_get_display_language($lang, $lang);
        $select .= '<option class="fb--conf--language" value="' . htmlspecialchars($lang) . '" ' . (($current_lang === $lang) ? ' selected' : '') . '>' . htmlspecialchars($lang_name) . '</option>';
      }
    }
    return $select;
  }

  public function renderConfigurationTokens(): string {
    $tokens_table = '<table>';
    $tokens = Token::allTokens();
    foreach ($tokens as $token) {
      if ($token->getUsed()) {
        $team = MultiTeam::team($token->getTeamId()); // TODO: Combine Awaits
        $token_status =
          '<span class="highlighted--red">' . htmlspecialchars(tr('Used by')) . htmlspecialchars($team->getName()) . '</span>';
      } else {
        $token_status =
          '<span class="highlighted--green">' . htmlspecialchars(tr('Available')) . '</span>';
      }
      $tokens_table .= '<tr>' . '<td>' . htmlspecialchars($token->getToken()) . '</td>' . '<td>' . $token_status . '</td>' . '</tr>';
      $tokens_table .= '</table>';
    }

    return
      '<div class="radio-tab-content" data-tab="reg_tokens">' . '<div class="admin-sections">' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Registration Tokens')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . $tokens_table . '</div>' . '<div class="admin-buttons admin-row">' . '<div class="button-right">' . '<button class="fb-cta cta--yellow" data-action="create-tokens">' . htmlspecialchars(tr('Create More')) . '</button>' . '<button class="fb-cta cta--yellow" data-action="export-tokens">' . htmlspecialchars(tr('Export Available')) . '</button>' . '</div>' . '</div>' . '</section>' . '</div>' . '</div>';
  }

  public function renderConfigurationContent(): string {
    $awaitables = [
      'game' => Configuration::get('game'),
      'registration' => Configuration::get('registration'),
      'registration_players' => Configuration::get('registration_players'),
      'login' => Configuration::get('login'),
      'login_select' => Configuration::get('login_select'),
      'login_strongpasswords' => Configuration::get('login_strongpasswords'),
      'login_facebook' => Configuration::get('login_facebook'),
      'login_google' => Configuration::get('login_google'),
      'registration_names' => Configuration::get('registration_names'),
      'registration_facebook' => Configuration::get('registration_facebook'),
      'registration_google' => Configuration::get('registration_google'),
      'registration_prefix' => Configuration::get('registration_prefix'),
      'ldap' => Configuration::get('ldap'),
      'ldap_server' => Configuration::get('ldap_server'),
      'ldap_port' => Configuration::get('ldap_port'),
      'ldap_domain_suffix' => Configuration::get('ldap_domain_suffix'),
      'scoring' => Configuration::get('scoring'),
      'gameboard' => Configuration::get('gameboard'),
      'auto_announce' => Configuration::get('auto_announce'),
      'timer' => Configuration::get('timer'),
      'progressive_cycle' => Configuration::get('progressive_cycle'),
      'default_bonus' => Configuration::get('default_bonus'),
      'default_bonusdec' => Configuration::get('default_bonusdec'),
      'gameboard_cycle' => Configuration::get('gameboard_cycle'),
      'conf_cycle' => Configuration::get('conf_cycle'),
      'leaderboard_limit' => Configuration::get('leaderboard_limit'),
      'bases_cycle' => Configuration::get('bases_cycle'),
      'autorun_cycle' => Configuration::get('autorun_cycle'),
      'start_ts' => Configuration::get('start_ts'),
      'end_ts' => Configuration::get('end_ts'),
      'livesync' => Configuration::get('livesync'),
      'livesync_auth_key' => Configuration::get('livesync_auth_key'),
      'custom_logo' => Configuration::get('custom_logo'),
      'custom_org' => Configuration::get('custom_org'),
      'custom_byline' => Configuration::get('custom_byline'),
      'custom_logo_image' => Configuration::get('custom_logo_image'),
    ];

    $results = $awaitables;

    $game = $results['game'];
    $registration = $results['registration'];
    $registration_players = $results['registration_players'];
    $login = $results['login'];
    $login_select = $results['login_select'];
    $login_strongpasswords = $results['login_strongpasswords'];
    $login_facebook = $results['login_facebook'];
    $login_google = $results['login_google'];
    $registration_names = $results['registration_names'];
    $registration_facebook = $results['registration_facebook'];
    $registration_google = $results['registration_google'];
    $registration_prefix = $results['registration_prefix'];
    $login_google = $results['login_google'];
    $ldap = $results['ldap'];
    $ldap_server = $results['ldap_server'];
    $ldap_port = $results['ldap_port'];
    $ldap_domain_suffix = $results['ldap_domain_suffix'];
    $scoring = $results['scoring'];
    $gameboard = $results['gameboard'];
    $auto_announce = $results['auto_announce'];
    $timer = $results['timer'];
    $progressive_cycle = $results['progressive_cycle'];
    $default_bonus = $results['default_bonus'];
    $default_bonusdec = $results['default_bonusdec'];
    $gameboard_cycle = $results['gameboard_cycle'];
    $conf_cycle = $results['conf_cycle'];
    $leaderboard_limit = $results['leaderboard_limit'];
    $bases_cycle = $results['bases_cycle'];
    $autorun_cycle = $results['autorun_cycle'];
    $start_ts = $results['start_ts'];
    $end_ts = $results['end_ts'];
    $livesync = $results['livesync'];
    $livesync_auth_key = $results['livesync_auth_key'];
    $custom_logo = $results['custom_logo'];
    $custom_org = $results['custom_org'];
    $custom_byline = $results['custom_byline'];
    $custom_logo_image = $results['custom_logo_image'];
    $registration_on = $registration->getValue() === '1';
    $registration_off = $registration->getValue() === '0';
    $login_on = $login->getValue() === '1';
    $login_off = $login->getValue() === '0';
    $login_select_on = $login_select->getValue() === '1';
    $login_select_off = $login_select->getValue() === '0';
    $login_facebook_on = $login_facebook->getValue() === '1';
    $login_facebook_off = $login_facebook->getValue() === '0';
    $login_google_on = $login_google->getValue() === '1';
    $login_google_off = $login_google->getValue() === '0';
    $registration_facebook_on = $registration_facebook->getValue() === '1';
    $registration_facebook_off = $registration_facebook->getValue() === '0';
    $registration_google_on = $registration_google->getValue() === '1';
    $registration_google_off = $registration_google->getValue() === '0';
    $ldap_on = $ldap->getValue() === '1';
    $ldap_off = $ldap->getValue() === '0';
    $strong_passwords_on = $login_strongpasswords->getValue() === '1';
    $strong_passwords_off = $login_strongpasswords->getValue() === '0';
    $registration_names_on = $registration_names->getValue() === '1';
    $registration_names_off = $registration_names->getValue() === '0';
    $scoring_on = $scoring->getValue() === '1';
    $scoring_off = $scoring->getValue() === '0';
    $gameboard_on = $gameboard->getValue() === '1';
    $gameboard_off = $gameboard->getValue() === '0';
    $auto_announce_on = $auto_announce->getValue() === '1';
    $auto_announce_off = $auto_announce->getValue() === '0';
    $timer_on = $timer->getValue() === '1';
    $timer_off = $timer->getValue() === '0';
    $livesync_on = $livesync->getValue() === '1';
    $livesync_off = $livesync->getValue() === '0';
    $custom_logo_on = $custom_logo->getValue() === '1';
    $custom_logo_off = $custom_logo->getValue() === '0';

    $game_start_array = [];
    if ($start_ts->getValue() !== '0' && $start_ts->getValue() !== 'NaN') {
      $game_start_ts = (int)$start_ts->getValue();
      $game_start_array = [];
      $game_start_array['year'] = gmdate('Y', $game_start_ts);
      $game_start_array['mon'] = gmdate('m', $game_start_ts);
      $game_start_array['mday'] = gmdate('d', $game_start_ts);
      $game_start_array['hours'] = gmdate('H', $game_start_ts);
      $game_start_array['minutes'] = gmdate('i', $game_start_ts);
    } else {
      $game_start_ts = '0';
      $game_start_array['year'] = '0';
      $game_start_array['mon'] = '0';
      $game_start_array['mday'] = '0';
      $game_start_array['hours'] = '0';
      $game_start_array['minutes'] = '0';
    }

    $game_end_array = [];
    if ($end_ts->getValue() !== '0' && $end_ts->getValue() !== 'NaN') {
      $game_end_ts = (int)$end_ts->getValue();
      $game_end_array = [];
      $game_end_array['year'] = gmdate('Y', $game_end_ts);
      $game_end_array['mon'] = gmdate('m', $game_end_ts);
      $game_end_array['mday'] = gmdate('d', $game_end_ts);
      $game_end_array['hours'] = gmdate('H', $game_end_ts);
      $game_end_array['minutes'] = gmdate('i', $game_end_ts);
    } else {
      $game_end_ts = '0';
      $game_end_array['year'] = '0';
      $game_end_array['mon'] = '0';
      $game_end_array['mday'] = '0';
      $game_end_array['hours'] = '0';
      $game_end_array['minutes'] = '0';
    }

    if ($game->getValue() === '0') {
      $timer_start_ts = tr('Not started yet');
      $timer_end_ts = tr('Not started yet');
      $game_schedule_reset_text = tr('Reset Schedule');
      $game_schedule_reset_class = 'fb-cta cta--red';
      $game_schedule_reset_action = 'reset-game-schedule';
    } else {
      $timer_start_ts =
        date(tr('date and time format'), (int)$start_ts->getValue());
      $timer_end_ts = date(tr('date and time format'), (int)$end_ts->getValue());
      $game_schedule_reset_text = tr('Game Running');
      $game_schedule_reset_class = 'fb-cta cta--yellowe';
      $game_schedule_reset_action = '';
    }

    $registration_type = Configuration::get('registration_type');
    if ($registration_type->getValue() === '2') { // Registration is tokenized
      $registration_tokens = $this->renderConfigurationTokens();
      $tabs_conf =
        '<div class="radio-tabs">' . '<input type="radio" value="reg_conf" name="fb--admin--tabs--conf" id="fb--admin--tabs--conf--conf" checked />' . '<label for="fb--admin--tabs--conf--conf">' . htmlspecialchars(tr('Configuration')) . '</label>' . '<input type="radio" value="reg_tokens" name="fb--admin--tabs--conf" id="fb--admin--tabs--conf--tokens" />' . '<label id="fb--admin--tabs--conf--tokens-label" for="fb--admin--tabs--conf--tokens">' . htmlspecialchars(tr('Tokens')) . '</label>' . '</div>';
    } else {
      $tabs_conf = '<div class="radio-tabs">' . '</div>';
      $registration_tokens = '<div>' . '</div>';
    }

    $awaitables = [
      'registration_type_select' => $this->registrationTypeSelect(),
      'configuration_duration_select' =>
        $this->configurationDurationSelect(),
      'language_select' => $this->languageSelect(),
      'password_types_select' => $this->strongPasswordsSelect(),
    ];
    $results = $awaitables;

    $registration_type_select = $results['registration_type_select'];
    $configuration_duration_select =
      $results['configuration_duration_select'];
    $language_select = $results['language_select'];
    $password_types_select = $results['password_types_select'];

    if ($login_strongpasswords->getValue() === '0') { // Strong passwords are not enforced
      $strong_passwords = '<div>' . '</div>';
    } else {
      $strong_passwords =
        '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Password Types')) . '</label>' . $password_types_select . '</div>';
    }

    if ($custom_logo->getValue() === '0') { // Custom branding is not enabled
      $custom_logo_xhp = '<div>' . '</div>';
    } else {
      $custom_logo_xhp =
        '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Logo')) . '</label>' . '<img id="custom-logo-image" class="icon--badge" src="' . htmlspecialchars($custom_logo_image->getValue()) . '" />' . '<br />' . '<h6>' . '<a class="icon-text" href="#" id="custom-logo-link">' . htmlspecialchars(tr('Change')) . '</a>' . '</h6>' . '<input autocomplete="off" name="custom-logo-input" id="custom-logo-input" type="file" accept="image/*" />' . '</div>';
    }

    return
      '<div>' . '<header class="admin-page-header">' . '<h3>' . htmlspecialchars(tr('Game Configuration')) . '</h3>' . '<span class="admin-section--status">' . htmlspecialchars(tr('status_')) . '<span class="highlighted">' . htmlspecialchars(tr('OK')) . '</span>' . '</span>' . '</header>' . $tabs_conf . '<div class="tab-content-container">' . '<div class="radio-tab-content active" data-tab="reg_conf">' . '<div class="admin-sections">' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Registration')) . '</h3>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="fb--conf--registration" id="fb--conf--registration--on" ' . ($registration_on ? ' checked' : '') . ' />' . '<label for="fb--conf--registration--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--conf--registration" id="fb--conf--registration--off" ' . ($registration_off ? ' checked' : '') . ' />' . '<label for="fb--conf--registration--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Player Names')) . '</label>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="fb--conf--registration_names" id="fb--conf--registration_names--on" ' . ($registration_names_on ? ' checked' : '') . ' />' . '<label for="fb--conf--registration_names--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--conf--registration_names" id="fb--conf--registration_names--off" ' . ($registration_names_off ? ' checked' : '') . ' />' . '<label for="fb--conf--registration_names--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-2-4">' . '<div class="form-el el--block-label">' . '<label for="">' . htmlspecialchars(tr('Players Per Team')) . '</label>' . '<input type="number" value="' . htmlspecialchars($registration_players->getValue()) . '" name="fb--conf--registration_players" max="12" min="1" />' . '</div>' . '</div>' . '<div class="col col-pad col-3-4">' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Registration Type')) . '</label>' . $registration_type_select . '</div>' . '</div>' . '</div>' . '</section>' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Login')) . '</h3>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="fb--conf--login" id="fb--conf--login--on" ' . ($login_on ? ' checked' : '') . ' />' . '<label for="fb--conf--login--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--conf--login" id="fb--conf--login--off" ' . ($login_off ? ' checked' : '') . ' />' . '<label for="fb--conf--login--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-3">' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Team Selection')) . '</label>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="fb--conf--login_select" id="fb--conf--login_select--on" ' . ($login_select_on ? ' checked' : '') . ' />' . '<label for="fb--conf--login_select--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--conf--login_select" id="fb--conf--login_select--off" ' . ($login_select_off ? ' checked' : '') . ' />' . '<label for="fb--conf--login_select--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-3">' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Strong Passwords')) . '</label>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="fb--conf--login_strongpasswords" id="fb--conf--login_strongpasswords--on" ' . ($strong_passwords_on ? ' checked' : '') . ' />' . '<label for="fb--conf--login_strongpasswords--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--conf--login_strongpasswords" id="fb--conf--login_strongpasswords--off" ' . ($strong_passwords_off ? ' checked' : '') . ' />' . '<label for="fb--conf--login_strongpasswords--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-2-3">' . $strong_passwords . '</div>' . '</div>' . '</section>' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Integration')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Facebook Login')) . '</label>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="fb--conf--login_facebook" id="fb--conf--login_facebook--on" ' . ($login_facebook_on ? ' checked' : '') . ' />' . '<label for="fb--conf--login_facebook--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--conf--login_facebook" id="fb--conf--login_facebook--off" ' . ($login_facebook_off ? ' checked' : '') . ' />' . '<label for="fb--conf--login_facebook--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</div>' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Facebook Registration')) . '</label>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="fb--conf--registration_facebook" id="fb--conf--registration_facebook--on" ' . ($registration_facebook_on ? ' checked' : '') . ' />' . '<label for="fb--conf--registration_facebook--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--conf--registration_facebook" id="fb--conf--registration_facebook--off" ' . ($registration_facebook_off ? ' checked' : '') . ' />' . '<label for="fb--conf--registration_facebook--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-2-4">' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Google Login')) . '</label>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="fb--conf--login_google" id="fb--conf--login_google--on" ' . ($login_google_on ? ' checked' : '') . ' />' . '<label for="fb--conf--login_google--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--conf--login_google" id="fb--conf--login_google--off" ' . ($login_google_off ? ' checked' : '') . ' />' . '<label for="fb--conf--login_google--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</div>' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Google Registration')) . '</label>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="fb--conf--registration_google" id="fb--conf--registration_google--on" ' . ($registration_google_on ? ' checked' : '') . ' />' . '<label for="fb--conf--registration_google--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--conf--registration_google" id="fb--conf--registration_google--off" ' . ($registration_google_off ? ' checked' : '') . ' />' . '<label for="fb--conf--registration_google--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-3-4">' . '<div class="form-el el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Automatic Team Name Prefix')) . '</label>' . '<input type="text" value="' . htmlspecialchars($registration_prefix->getValue()) . '" name="fb--conf--registration_prefix" />' . '</div>' . '</div>' . '</div>' . '</section>' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Active Directory / LDAP')) . '</h3>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="fb--conf--ldap" id="fb--conf--ldap--on" ' . ($ldap_on ? ' checked' : '') . ' />' . '<label for="fb--conf--ldap--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--conf--ldap" id="fb--conf--ldap--off" ' . ($ldap_off ? ' checked' : '') . ' />' . '<label for="fb--conf--ldap--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('LDAP Server')) . '</label>' . '<input type="text" value="' . htmlspecialchars($ldap_server->getValue()) . '" name="fb--conf--ldap_server" />' . '</div>' . '</div>' . '<div class="col col-pad col-2-4">' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('LDAP Port')) . '</label>' . '<input type="number" min="1" max="65535" value="' . htmlspecialchars($ldap_port->getValue()) . '" name="fb--conf--ldap_port" />' . '</div>' . '</div>' . '<div class="col col-pad col-3-4">' . '<div class="form-el el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('LDAP Domain')) . '</label>' . '<input type="text" value="' . htmlspecialchars($ldap_domain_suffix->getValue()) . '" name="fb--conf--ldap_domain_suffix" />' . '</div>' . '</div>' . '</div>' . '</section>' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Game')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Scoring')) . '</label>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="fb--conf--scoring" id="fb--conf--scoring--on" ' . ($scoring_on ? ' checked' : '') . ' />' . '<label for="fb--conf--scoring--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--conf--scoring" id="fb--conf--scoring--off" ' . ($scoring_off ? ' checked' : '') . ' />' . '<label for="fb--conf--scoring--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</div>' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Progressive Cycle (s)')) . '</label>' . '<input type="number" value="' . htmlspecialchars($progressive_cycle->getValue()) . '" name="fb--conf--progressive_cycle" />' . '</div>' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Bases Cycle (s)')) . '</label>' . '<input type="number" value="' . htmlspecialchars($bases_cycle->getValue()) . '" name="fb--conf--bases_cycle" />' . '</div>' . '</div>' . '<div class="col col-pad col-2-4">' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Refresh Gameboard')) . '</label>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="fb--conf--gameboard" id="fb--conf--gameboard--on" ' . ($gameboard_on ? ' checked' : '') . ' />' . '<label for="fb--conf--gameboard--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--conf--gameboard" id="fb--conf--gameboard--off" ' . ($gameboard_off ? ' checked' : '') . ' />' . '<label for="fb--conf--gameboard--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</div>' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Default Bonus')) . '</label>' . '<input type="number" value="' . htmlspecialchars($default_bonus->getValue()) . '" name="fb--conf--default_bonus" />' . '</div>' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Gameboard Cycle (s)')) . '</label>' . '<input type="number" value="' . htmlspecialchars($gameboard_cycle->getValue()) . '" name="fb--conf--gameboard_cycle" />' . '</div>' . '</div>' . '<div class="col col-pad col-3-4">' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Auto Announcements')) . '</label>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="fb--conf--auto_announce" id="fb--conf--auto_announce--on" ' . ($auto_announce_on ? ' checked' : '') . ' />' . '<label for="fb--conf--auto_announce--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--conf--auto_announce" id="fb--conf--auto_announce--off" ' . ($auto_announce_off ? ' checked' : '') . ' />' . '<label for="fb--conf--auto_announce--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</div>' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Default Bonus Dec')) . '</label>' . '<input type="number" value="' . htmlspecialchars($default_bonusdec->getValue()) . '" name="fb--conf--default_bonusdec" />' . '</div>' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Configuration Cycle (s)')) . '</label>' . '<input type="number" value="' . htmlspecialchars($conf_cycle->getValue()) . '" name="fb--conf--conf_cycle" />' . '</div>' . '</div>' . '<div class="col col-pad col-4-4">' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Autorun Cycle (s)')) . '</label>' . '<input type="number" value="' . htmlspecialchars($autorun_cycle->getValue()) . '" name="fb--conf--autorun_cycle" />' . '</div>' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Leaderboard Limit')) . '</label>' . '<input type="number" value="' . htmlspecialchars($leaderboard_limit->getValue()) . '" name="fb--conf--leaderboard_limit" />' . '</div>' . '<div class="form-el el--block-label">' . '</div>' . '</div>' . '</div>' . '</section>' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Game Schedule')) . '</h3>' . '<div class="admin-section-toggle radio-inline">' . '<button class="' . htmlspecialchars(strval($game_schedule_reset_class)) . '" data-action="' . htmlspecialchars($game_schedule_reset_action) . '">' . htmlspecialchars($game_schedule_reset_text) . '</button>' . '</div>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-5">' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Game Start Year')) . '</label>' . '<input type="number" value="' . htmlspecialchars(strval($game_start_array['year'])) . '" name="fb--schedule--start_year" />' . '</div>' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Game End Year')) . '</label>' . '<input type="number" value="' . htmlspecialchars(strval($game_end_array['year'])) . '" name="fb--schedule--end_year" />' . '</div>' . '</div>' . '<div class="col col-pad col-2-5">' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Month')) . '</label>' . '<input type="number" value="' . htmlspecialchars(strval($game_start_array['mon'])) . '" name="fb--schedule--start_month" />' . '</div>' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Month')) . '</label>' . '<input type="number" value="' . htmlspecialchars(strval($game_end_array['mon'])) . '" name="fb--schedule--end_month" />' . '</div>' . '</div>' . '<div class="col col-pad col-3-5">' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Day')) . '</label>' . '<input type="number" value="' . htmlspecialchars(strval($game_start_array['mday'])) . '" name="fb--schedule--start_day" />' . '</div>' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Day')) . '</label>' . '<input type="number" value="' . htmlspecialchars(strval($game_end_array['mday'])) . '" name="fb--schedule--end_day" />' . '</div>' . '</div>' . '<div class="col col-pad col-4-5">' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Hour')) . '</label>' . '<input type="number" value="' . htmlspecialchars(strval($game_start_array['hours'])) . '" name="fb--schedule--start_hour" />' . '</div>' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Hour')) . '</label>' . '<input type="number" value="' . htmlspecialchars(strval($game_end_array['hours'])) . '" name="fb--schedule--end_hour" />' . '</div>' . '</div>' . '<div class="col col-pad col-5-5">' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Minute')) . '</label>' . '<input type="number" value="' . htmlspecialchars(strval($game_start_array['minutes'])) . '" name="fb--schedule--start_min" />' . '</div>' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Minute')) . '</label>' . '<input type="number" value="' . htmlspecialchars(strval($game_end_array['minutes'])) . '" name="fb--schedule--end_min" />' . '</div>' . '</div>' . '</div>' . '</section>' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Timer')) . '</h3>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="fb--conf--timer" id="fb--conf--timer--on" ' . ($timer_on ? ' checked' : '') . ' />' . '<label for="fb--conf--timer--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--conf--timer" id="fb--conf--timer--off" ' . ($timer_off ? ' checked' : '') . ' />' . '<label for="fb--conf--timer--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Server Time')) . '</label>' . '<input type="text" value="' . htmlspecialchars(date(tr('date and time format'), time())) . '" name="fb--conf--server_time" disabled />' . '</div>' . '</div>' . '<div class="col col-pad col-2-4">' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Game Duration')) . '</label>' . $configuration_duration_select . '</div>' . '</div>' . '<div class="col col-pad col-3-4">' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Begin Time')) . '</label>' . '<input type="text" value="' . htmlspecialchars($timer_start_ts) . '" id="fb--conf--start_ts" disabled />' . '</div>' . '</div>' . '<div class="col col-pad col-4-4">' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Expected End Time')) . '</label>' . '<input type="text" value="' . htmlspecialchars($timer_end_ts) . '" id="fb--conf--end_ts" disabled />' . '</div>' . '</div>' . '</div>' . '</section>' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('LiveSync')) . '</h3>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="fb--conf--livesync" id="fb--conf--livesync--on" ' . ($livesync_on ? ' checked' : '') . ' />' . '<label for="fb--conf--livesync--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--conf--livesync" id="fb--conf--livesync--off" ' . ($livesync_off ? ' checked' : '') . ' />' . '<label for="fb--conf--livesync--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Optional LiveSync Auth Key')) . '</label>' . '<input type="text" value="' . htmlspecialchars($livesync_auth_key->getValue()) . '" name="fb--conf--livesync_auth_key" />' . '</div>' . '</div>' . '</div>' . '</section>' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Internationalization')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-2-4">' . '<div class="form-el el--block-label">' . '<label for="">' . htmlspecialchars(tr('Language')) . '</label>' . $language_select . '</div>' . '</div>' . '</div>' . '</section>' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Branding')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-3">' . '<div class="form-el el--block-label">' . '<label>' . htmlspecialchars(tr('Custom Logo')) . '</label>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="fb--conf--custom_logo" id="fb--conf--custom_logo--on" ' . ($custom_logo_on ? ' checked' : '') . ' />' . '<label for="fb--conf--custom_logo--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--conf--custom_logo" id="fb--conf--custom_logo--off" ' . ($custom_logo_off ? ' checked' : '') . ' />' . '<label for="fb--conf--custom_logo--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-3">' . $custom_logo_xhp . '</div>' . '<div class="col col-pad col-1-3">' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Custom Organization')) . '</label>' . '<input type="text" name="fb--conf--custom_org" value="' . htmlspecialchars($custom_org->getValue()) . '" />' . '</div>' . '</div>' . '<div class="col col-pad col-1-3">' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Custom Byline')) . '</label>' . '<input type="text" name="fb--conf--custom_byline" value="' . htmlspecialchars($custom_byline->getValue()) . '" />' . '</div>' . '</div>' . '</div>' . '</section>' . '</div>' . '</div>' . $registration_tokens . '</div>' . '</div>';
  }

  public function renderAnnouncementsContent(): string {
    $announcements = Announcement::allAnnouncements();
    $announcements_div = '<div>';
    if ($announcements) {
      foreach ($announcements as $announcement) {
        $announcements_div .= '<section class="admin-box">' . '<form class="announcements_form">' . '<input type="hidden" name="announcement_id" value="' . htmlspecialchars(strval($announcement->getId())) . '" />' . '<header class="management-header">' . '<h6>' . htmlspecialchars(time_ago($announcement->getTs())) . '</h6>' . '<a class="highlighted--red" href="#" data-action="delete">' . htmlspecialchars(tr('DELETE')) . '</a>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad">' . '<div class="selected-logo">' . '<span class="logo-name">' . htmlspecialchars($announcement->getAnnouncement()) . '</span>' . '</div>' . '</div>' . '</div>' . '</form>' . '</section>';
      }
    } else {
      $announcements_div .= '<section class="admin-box">' . '<div class="fb-column-container">' . '<div class="col col-pad">' . '<div class="selected-logo-text">' . '<span class="logo-name">' . htmlspecialchars(tr('No Announcements')) . '</span>' . '</div>' . '</div>' . '</div>' . '</section>';
      $announcements_div .= '</div>';
    }
    return
      '<div>' . '<header class="admin-page-header">' . '<h3>' . htmlspecialchars(tr('Announcement Controls')) . '</h3>' . '<span class="admin-section--status">' . htmlspecialchars(tr('status_')) . '<span class="highlighted">' . htmlspecialchars(tr('OK')) . '</span>' . '</span>' . '</header>' . '<div class="admin-sections">' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Announcements')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-3-4">' . '<div class="form-el el--block-label el--full-text">' . '<input type="text" name="new_announcement" placeholder="' . htmlspecialchars(tr('Write New Announcement here')) . '" value="" />' . '</div>' . '</div>' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--yellow" data-action="create-announcement">' . htmlspecialchars(tr('Create')) . '</button>' . '</div>' . '</div>' . '</div>' . '</div>' . '</section>' . $announcements_div . '</div>' . '</div>';
  }

  public function renderControlsContent(): string {
    return
      '<div>' . '<header class="admin-page-header">' . '<h3>' . 'Game Controls' . '</h3>' . '<span class="admin-section--status">' . htmlspecialchars(tr('status_')) . '<span class="highlighted">' . htmlspecialchars(tr('OK')) . '</span>' . '</span>' . '</header>' . '<div class="admin-sections">' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('General')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-3">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--red" data-action="import-game">' . htmlspecialchars(tr('Import Full Game')) . '</button>' . '<input class="completely-hidden" id="import-game_file" type="file" name="game_file" />' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-3">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--yellow" data-action="export-game">' . htmlspecialchars(tr('Export Full Game')) . '</button>' . '</div>' . '</div>' . '</div>' . '</div>' . '</section>' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Utilities')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--yellow" data-action="flush-memcached">' . htmlspecialchars(tr('Flush Memcached')) . '</button>' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--red js-reset-database">' . htmlspecialchars(tr('Reset Database')) . '</button>' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-3">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--red js-restore-database">' . htmlspecialchars(tr('Restore Database')) . '</button>' . '<input class="completely-hidden" id="restore-database_file" type="file" name="database_file" />' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-3">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--yellow" data-action="backup-db">' . htmlspecialchars(tr('Backup Database')) . '</button>' . '</div>' . '</div>' . '</div>' . '</div>' . '</section>' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Teams')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--red" data-action="import-teams">' . htmlspecialchars(tr('Import Teams')) . '</button>' . '<input class="completely-hidden" id="import-teams_file" type="file" name="teams_file" />' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--yellow" data-action="export-teams">' . htmlspecialchars(tr('Export Teams')) . '</button>' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--red" data-action="import-logos">' . htmlspecialchars(tr('Import Logos')) . '</button>' . '<input class="completely-hidden" id="import-logos_file" type="file" name="logos_file" />' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--yellow" data-action="export-logos">' . htmlspecialchars(tr('Export Logos')) . '</button>' . '</div>' . '</div>' . '</div>' . '</div>' . '</section>' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Levels')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--red" data-action="import-levels">' . htmlspecialchars(tr('Import Levels')) . '</button>' . '<input class="completely-hidden" id="import-levels_file" type="file" name="levels_file" />' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--yellow" data-action="export-levels">' . htmlspecialchars(tr('Export Levels')) . '</button>' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--red" data-action="import-attachments">' . htmlspecialchars(tr('Import Attachments')) . '</button>' . '<input class="completely-hidden" id="import-attachments_file" type="file" name="attachments_file" />' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--yellow" data-action="export-attachments">' . htmlspecialchars(tr('Export Attachments')) . '</button>' . '</div>' . '</div>' . '</div>' . '</div>' . '</section>' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Categories')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--red" data-action="import-categories">' . htmlspecialchars(tr('Import Categories')) . '</button>' . '<input class="completely-hidden" id="import-categories_file" type="file" name="categories_file" />' . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-4">' . '<div class="form-el el--block-label el--full-text">' . '<div class="admin-buttons">' . '<button class="fb-cta cta--yellow" data-action="export-categories">' . htmlspecialchars(tr('Export Categories')) . '</button>' . '</div>' . '</div>' . '</div>' . '</div>' . '</section>' . '</div>' . '</div>';
  }

  public function renderQuizContent(): string {
    $countries_select = $this->generateCountriesSelect(0);
    $adminsections =
      '<div class="admin-sections">' . '<section id="new-element" class="validate-form admin-box completely-hidden">' . '<form class="level_form quiz_form">' . '<input type="hidden" name="level_type" value="quiz" />' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('New Quiz Level')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-2">' . '<div class="form-el form-el--required el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Title')) . '</label>' . '<input name="title" type="text" placeholder="' . htmlspecialchars(tr('Level title')) . '" />' . '</div>' . '<div class="form-el form-el--required el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Question')) . '</label>' . '<textarea name="question" placeholder="' . htmlspecialchars(tr('Quiz question')) . '" rows="' . "4" . '">' . '</textarea>' . '</div>' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Country')) . '</label>' . $countries_select . '</div>' . '</div>' . '<div class="col col-pad col-1-2">' . '<div class="form-el fb-column-container col-gutters">' . '<div class="form-el--required col col-2-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Answer')) . '</label>' . '<input name="answer" type="text" />' . '</div>' . '<div class="form-el--required col col-1-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Points')) . '</label>' . '<input name="points" type="text" />' . '</div>' . '</div>' . '<div class="form-el fb-column-container col-gutters">' . '<div class="col col-2-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Hint')) . '</label>' . '<input name="hint" type="text" />' . '</div>' . '<div class="col col-1-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Hint Penalty')) . '</label>' . '<input name="penalty" type="text" />' . '</div>' . '</div>' . '</div>' . '</div>' . '<div class="admin-buttons admin-row">' . '<div class="button-right">' . '<a href="#" class="admin--edit" data-action="edit">' . htmlspecialchars(tr('EDIT')) . '</a>' . '<button class="fb-cta cta--red" data-action="delete">' . htmlspecialchars(tr('Delete')) . '</button>' . '<button class="fb-cta cta--yellow" data-action="create">' . htmlspecialchars(tr('Create')) . '</button>' . '</div>' . '</div>' . '</form>' . '</section>' . '<section id="new-element" class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('All Quiz Levels')) . '</h3>' . '<form class="all_quiz_form">' . '<div class="admin-section-toggle radio-inline col">' . '<input type="radio" name="fb--levels--all_quiz" id="fb--levels--all_quiz--on" />' . '<label for="fb--levels--all_quiz--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--levels--all_quiz" id="fb--levels--all_quiz--off" />' . '<label for="fb--levels--all_quiz--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</form>' . '</header>' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Filter By:')) . '</h3>' . '<div class="form-el fb-column-container col-gutters">' . '<div class="col col-1-5 el--block-label el--full-text">' . '</div>' . '<div class="col col-1-5 el--block-label el--full-text">' . '<select class="not_configuration" name="status_filter">' . '<option class="filter_option" value="all">' . htmlspecialchars(tr('All Status')) . '</option>' . '<option class="filter_option" value="Enabled">' . htmlspecialchars(tr('Enabled')) . '</option>' . '<option class="filter_option" value="Disabled">' . htmlspecialchars(tr('Disabled')) . '</option>' . '</select>' . '</div>' . '<div class="col col-1-5 el--block-label el--full-text">' . '</div>' . '<div class="col col-1-5 el--block-label el--full-text">' . '</div>' . '<div class="col col-1-5 el--block-label el--full-text">' . '</div>' . '</div>' . '</header>' . '</section>' . '</div>';

    $c = 1;
    $quizes = Level::allQuizLevels();
    foreach ($quizes as $quiz) {
      $quiz_active_on = ($quiz->getActive());
      $quiz_active_off = (!$quiz->getActive());

      $quiz_status_name =
        'fb--levels--level-'.strval($quiz->getId()).'-status';
      $quiz_status_on_id =
        'fb--levels--level-'.strval($quiz->getId()).'-status--on';
      $quiz_status_off_id =
        'fb--levels--level-'.strval($quiz->getId()).'-status--off';

      $quiz_id = strval($quiz->getId());
      $quiz_id_txt = 'quiz_id'.strval($quiz->getId());

      $countries_select =
        $this->generateCountriesSelect($quiz->getEntityId()); // TODO: Combine Awaits

      $delete_button =
        '<div style="display: inline">' . '<input type="hidden" name="level_id" value="' . htmlspecialchars($quiz_id) . '" />' . '<a href="#" class="fb-cta cta--red js-delete-level" style="margin-right: 20px">' . htmlspecialchars(tr('Delete')) . '</a>' . '</div>';

      $adminsections .= '<section class="admin-box validate-form section-locked">' . '<form class="level_form quiz_form" name="' . htmlspecialchars($quiz_id_txt) . '">' . '<input type="hidden" name="level_type" value="quiz" />' . '<input type="hidden" name="level_id" value="' . htmlspecialchars(strval($quiz->getId())) . '" />' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Quiz Level')) . htmlspecialchars((string)$c) . '</h3>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="' . htmlspecialchars($quiz_status_name) . '" id="' . htmlspecialchars($quiz_status_on_id) . '" ' . ($quiz_active_on ? ' checked' : '') . ' />' . '<label for="' . htmlspecialchars($quiz_status_on_id) . '">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="' . htmlspecialchars($quiz_status_name) . '" id="' . htmlspecialchars($quiz_status_off_id) . '" ' . ($quiz_active_off ? ' checked' : '') . ' />' . '<label for="' . htmlspecialchars($quiz_status_off_id) . '">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-2">' . '<div class="form-el form-el--required el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Title')) . '</label>' . '<input name="title" type="text" value="' . htmlspecialchars($quiz->getTitle()) . '" disabled />' . '</div>' . '<div class="form-el form-el--required el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Question')) . '</label>' . '<textarea name="question" rows="' . "6" . '" disabled>' . htmlspecialchars($quiz->getDescription()) . '</textarea>' . '</div>' . '<div class="form-el el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Country')) . '</label>' . $countries_select . '</div>' . '</div>' . '<div class="col col-pad col-1-2">' . '<div class="form-el form-el--required el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Answer')) . '</label>' . '<input name="answer" type="password" value="' . htmlspecialchars($quiz->getFlag()) . '" disabled />' . '<a href="" class="toggle_answer_visibility">' . htmlspecialchars(tr('Show Answer')) . '</a>' . '</div>' . '<div class="form-el fb-column-container col-gutters">' . '<div class="form-el--required col col-1-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Points')) . '</label>' . '<input name="points" type="text" value="' . htmlspecialchars(strval($quiz->getPoints())) . '" disabled />' . '</div>' . '<div class="form-el--required col col-1-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Bonus')) . '</label>' . '<input name="bonus" type="text" value="' . htmlspecialchars(strval($quiz->getBonus())) . '" disabled />' . '</div>' . '<div class="form-el--required col col-1-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('-Dec')) . '</label>' . '<input name="bonus_dec" type="text" value="' . htmlspecialchars(strval($quiz->getBonusDec())) . '" disabled />' . '</div>' . '</div>' . '<div class="form-el fb-column-container col-gutters">' . '<div class="col col-2-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Hint')) . '</label>' . '<input name="hint" type="text" value="' . htmlspecialchars($quiz->getHint()) . '" disabled />' . '</div>' . '<div class="col col-1-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Hint Penalty')) . '</label>' . '<input name="penalty" type="text" value="' . htmlspecialchars(strval($quiz->getPenalty())) . '" disabled />' . '</div>' . '</div>' . '</div>' . '</div>' . '<div class="admin-buttons admin-row">' . '<div class="button-right">' . '<a href="#" class="admin--edit" data-action="edit">' . htmlspecialchars(tr('EDIT')) . '</a>' . $delete_button . '<button class="fb-cta cta--yellow" data-action="save">' . htmlspecialchars(tr('Save')) . '</button>' . '</div>' . '</div>' . '</form>' . '</section>';
      $c++;
    }

    return
      '<div>' . '<header class="admin-page-header">' . '<h3>' . htmlspecialchars(tr('Quiz Management')) . '</h3>' . '<span class="admin-section--status">' . htmlspecialchars(tr('status_')) . '<span class="highlighted">' . htmlspecialchars(tr('OK')) . '</span>' . '</span>' . '</header>' . $adminsections . '<div class="admin-buttons">' . '<button class="fb-cta" data-action="add-new">' . htmlspecialchars(tr('Add Quiz Level')) . '</button>' . '</div>' . '</div>';
  }

  public function renderFlagsContent(): string {
    list(
      $countries_select,
      $level_categories_select,
      $filter_categories_select,
    ) = [
      $this->generateCountriesSelect(0),
      $this->generateLevelCategoriesSelect(0),
      $this->generateFilterCategoriesSelect(),
    ];

    $adminsections =
      '<div class="admin-sections">' . '<section id="new-element" class="validate-form admin-box completely-hidden">' . '<form class="level_form flag_form">' . '<input type="hidden" name="level_type" value="flag" />' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('New Flag Level')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-2">' . '<div class="form-el form-el--required el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Title')) . '</label>' . '<input name="title" type="text" placeholder="' . htmlspecialchars(tr('Level title')) . '" />' . '</div>' . '<div class="form-el form-el--required el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Description')) . '</label>' . '<textarea name="description" placeholder="' . htmlspecialchars(tr('Level description')) . '" rows="' . "4" . '">' . '</textarea>' . '</div>' . '<div class="form-el form-el--required fb-column-container col-gutters">' . '<div class="col col-1-2 el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Country')) . '</label>' . $countries_select . '</div>' . '<div class="col col-1-2 el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Category')) . '</label>' . $level_categories_select . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-2">' . '<div class="form-el fb-column-container col-gutters">' . '<div class="form-el--required col col-2-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Flag')) . '</label>' . '<input name="flag" type="text" />' . '</div>' . '<div class="form-el--required col col-1-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Points')) . '</label>' . '<input name="points" type="text" />' . '</div>' . '</div>' . '<div class="form-el fb-column-container col-gutters">' . '<div class="col col-2-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Hint')) . '</label>' . '<input name="hint" type="text" />' . '</div>' . '<div class="col col-1-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Hint Penalty')) . '</label>' . '<input name="penalty" type="text" />' . '</div>' . '</div>' . '</div>' . '</div>' . '<div class="admin-buttons admin-row">' . '<div class="button-right">' . '<a href="#" class="admin--edit" data-action="edit">' . htmlspecialchars(tr('EDIT')) . '</a>' . '<button class="fb-cta cta--red" data-action="delete">' . htmlspecialchars(tr('Delete')) . '</button>' . '<button class="fb-cta cta--yellow" data-action="create">' . htmlspecialchars(tr('Create')) . '</button>' . '</div>' . '</div>' . '</form>' . '</section>' . '<section id="new-element" class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('All Flag Levels')) . '</h3>' . '<form class="all_flag_form">' . '<div class="admin-section-toggle radio-inline col">' . '<input type="radio" name="fb--levels--all_flag" id="fb--levels--all_flag--on" />' . '<label for="fb--levels--all_flag--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--levels--all_flag" id="fb--levels--all_flag--off" />' . '<label for="fb--levels--all_flag--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</form>' . '</header>' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Filter By:')) . '</h3>' . '<div class="form-el fb-column-container col-gutters">' . '<div class="col col-1-5 el--block-label el--full-text">' . '</div>' . '<div class="col col-1-5 el--block-label el--full-text">' . $filter_categories_select . '</div>' . '<div class="col col-1-5 el--block-label el--full-text">' . '</div>' . '<div class="col col-1-5 el--block-label el--full-text">' . '<select class="not_configuration" name="status_filter">' . '<option class="filter_option" value="all">' . htmlspecialchars(tr('All Status')) . '</option>' . '<option class="filter_option" value="Enabled">' . htmlspecialchars(tr('Enabled')) . '</option>' . '<option class="filter_option" value="Disabled">' . htmlspecialchars(tr('Disabled')) . '</option>' . '</select>' . '</div>' . '<div class="col col-1-5 el--block-label el--full-text">' . '</div>' . '</div>' . '</header>' . '</section>' . '</div>';

    $c = 1;
    $flags = Level::allFlagLevels();
    foreach ($flags as $flag) {
      $flag_active_on = ($flag->getActive());
      $flag_active_off = (!$flag->getActive());

      $flag_status_name =
        'fb--levels--level-'.strval($flag->getId()).'-status';
      $flag_status_on_id =
        'fb--levels--level-'.strval($flag->getId()).'-status--on';
      $flag_status_off_id =
        'fb--levels--level-'.strval($flag->getId()).'-status--off';

      $flag_id_txt = 'flag_id'.strval($flag->getId());
      $flag_id = strval($flag->getId());

      $delete_button =
        '<div style="display: inline">' . '<input type="hidden" name="level_id" value="' . htmlspecialchars($flag_id) . '" />' . '<a href="#" class="fb-cta cta--red js-delete-level" style="margin-right: 20px">' . htmlspecialchars(tr('Delete')) . '</a>' . '</div>';

      $attachments_div =
        '<div class="attachments">' . '<div class="new-attachment new-attachment-hidden fb-column-container completely-hidden">' . '<div class="col col-pad col-1-3">' . '<div class="form-el">' . '<form class="attachment_form">' . '<input type="hidden" name="action" value="create_attachment" />' . '<input type="hidden" name="level_id" value="' . htmlspecialchars(strval($flag->getId())) . '" />' . '<div class="col el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('New Attachment:')) . '</label>' . '<input name="filename" type="text" />' . '<input name="attachment_file" type="file" />' . '</div>' . '</form>' . '</div>' . '</div>' . '<div class="admin-buttons col col-pad col-1-3">' . '<div class="col el--block-label el--full-text">' . '<button class="fb-cta cta--red" data-action="delete-new-attachment">' . 'X' . '</button>' . '<button class="fb-cta cta--yellow" data-action="create-attachment">' . htmlspecialchars(tr('Create')) . '</button>' . '</div>' . '</div>' . '</div>' . '</div>';

      $attachments = Attachment::hasAttachments($flag->getId()); // TODO: Combine Awaits
      if ($attachments) {
        $a_c = 1;
        $all_attachments =
          Attachment::allAttachments($flag->getId()); // TODO: Combine Awaits
        foreach ($all_attachments as $attachment) {
          $attachments_div .= '<div class="existing-attachment fb-column-container">' . '<div class="col col-pad col-2-3">' . '<div class="form-el">' . '<form class="attachment_form">' . '<input type="hidden" name="attachment_id" value="' . htmlspecialchars(strval($attachment->getId())) . '" />' . '<div class="col el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Attachment')) . htmlspecialchars((string)$a_c) . ':' . '</label>' . '<input name="filename" type="text" value="' . htmlspecialchars($attachment->getFilename()) . '" disabled />' . '<a href="' . htmlspecialchars($attachment->getFileLink()) . '" target="_blank">' . htmlspecialchars(tr('Link')) . '</a>' . '</div>' . '</form>' . '</div>' . '</div>' . '<div class="admin-buttons col col-pad col-1-3">' . '<div class="col el--block-label el--full-text">' . '<button class="fb-cta cta--red" data-action="delete-attachment">' . 'X' . '</button>' . '</div>' . '</div>' . '</div>';
          $a_c++;
        }
      }

      $links_div =
        '<div class="links">' . '<div class="new-link new-link-hidden fb-column-container completely-hidden">' . '<div class="col col-pad col-1-3">' . '<div class="form-el">' . '<form class="link_form">' . '<input type="hidden" name="action" value="create_link" />' . '<input type="hidden" name="level_id" value="' . htmlspecialchars(strval($flag->getId())) . '" />' . '<div class="col el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('New Link:')) . '</label>' . '<input name="link" type="text" />' . '</div>' . '</form>' . '</div>' . '</div>' . '<div class="admin-buttons col col-pad col-1-3">' . '<div class="col el--block-label el--full-text">' . '<button class="fb-cta cta--red" data-action="delete-new-link">' . 'X' . '</button>' . '<button class="fb-cta cta--yellow" data-action="create-link">' . htmlspecialchars(tr('Create')) . '</button>' . '</div>' . '</div>' . '</div>' . '</div>';

      $links = Link::hasLinks($flag->getId()); // TODO: Combine Awaits
      if ($links) {
        $l_c = 1;
        $all_links = Link::allLinks($flag->getId()); // TODO: Combine Awaits
        foreach ($all_links as $link) {
          $links_div .= '<div class="existing-link fb-column-container">' . '<div class="col col-pad col-2-3">' . '<div class="form-el">' . '<form class="link_form">' . '<input type="hidden" name="link_id" value="' . htmlspecialchars(strval($link->getId())) . '" />' . '<div class="col el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Link')) . htmlspecialchars((string)$l_c) . ':' . '</label>' . '<input name="link" type="text" value="' . htmlspecialchars($link->getLink()) . '" disabled />' . '<a href="' . htmlspecialchars($link->getLink()) . '" target="_blank">' . htmlspecialchars(tr('Link')) . '</a>' . '</div>' . '</form>' . '</div>' . '</div>' . '<div class="admin-buttons col col-pad col-1-3">' . '<div class="col el--block-label el--full-text">' . '<button class="fb-cta cta--red" data-action="delete-link">' . 'X' . '</button>' . '</div>' . '</div>' . '</div>';
          $l_c++;
        }
      }

      list($countries_select, $level_categories_select) = [
        $this->generateCountriesSelect($flag->getEntityId()),
        $this->generateLevelCategoriesSelect($flag->getCategoryId()),
      ]; // TODO: Combine Awaits

      $adminsections .= '<section class="validate-form admin-box section-locked">' . '<form class="level_form flag_form" name="' . htmlspecialchars($flag_id_txt) . '">' . '<input type="hidden" name="level_type" value="flag" />' . '<input type="hidden" name="level_id" value="' . htmlspecialchars(strval($flag->getId())) . '" />' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Flag Level')) . htmlspecialchars((string)$c) . '</h3>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="' . htmlspecialchars($flag_status_name) . '" id="' . htmlspecialchars($flag_status_on_id) . '" ' . ($flag_active_on ? ' checked' : '') . ' />' . '<label for="' . htmlspecialchars($flag_status_on_id) . '">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="' . htmlspecialchars($flag_status_name) . '" id="' . htmlspecialchars($flag_status_off_id) . '" ' . ($flag_active_off ? ' checked' : '') . ' />' . '<label for="' . htmlspecialchars($flag_status_off_id) . '">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-2">' . '<div class="form-el form-el--required el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Title')) . '</label>' . '<input name="title" type="text" value="' . htmlspecialchars($flag->getTitle()) . '" disabled />' . '</div>' . '<div class="form-el form-el--required el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Description')) . '</label>' . '<textarea name="description" rows="' . "6" . '" disabled>' . htmlspecialchars($flag->getDescription()) . '</textarea>' . '</div>' . '<div class="form-el fb-column-container col-gutters">' . '<div class="col col-1-2 el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Country')) . '</label>' . $countries_select . '</div>' . '<div class="col col-1-2 el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Categories')) . '</label>' . $level_categories_select . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-2">' . '<div class="form-el fb-column-container col-gutters">' . '<div class="form-el--required col el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Flag')) . '</label>' . '<input name="flag" type="password" value="' . htmlspecialchars($flag->getFlag()) . '" disabled />' . '<a href="" class="toggle_answer_visibility">' . htmlspecialchars(tr('Show Answer')) . '</a>' . '</div>' . '</div>' . '<div class="form-el fb-column-container col-gutters">' . '<div class="form-el--required col col-1-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Points')) . '</label>' . '<input name="points" type="text" value="' . htmlspecialchars(strval($flag->getPoints())) . '" disabled />' . '</div>' . '<div class="form-el--required col col-1-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Bonus')) . '</label>' . '<input name="bonus" type="text" value="' . htmlspecialchars(strval($flag->getBonus())) . '" disabled />' . '</div>' . '<div class="form-el--required col col-1-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('-Dec')) . '</label>' . '<input name="bonus_dec" type="text" value="' . htmlspecialchars(strval($flag->getBonusDec())) . '" disabled />' . '</div>' . '</div>' . '<div class="form-el fb-column-container col-gutters">' . '<div class="col col-2-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Hint')) . '</label>' . '<input name="hint" type="text" value="' . htmlspecialchars($flag->getHint()) . '" disabled />' . '</div>' . '<div class="col col-1-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Hint Penalty')) . '</label>' . '<input name="penalty" type="text" value="' . htmlspecialchars(strval($flag->getPenalty())) . '" disabled />' . '</div>' . '</div>' . '</div>' . '</div>' . '</form>' . $attachments_div . $links_div . '<div class="admin-buttons admin-row">' . '<div class="button-right">' . '<a href="#" class="admin--edit" data-action="edit">' . htmlspecialchars(tr('EDIT')) . '</a>' . $delete_button . '<button class="fb-cta cta--yellow" data-action="save">' . htmlspecialchars(tr('Save')) . '</button>' . '</div>' . '<div class="button-left">' . '<button class="fb-cta" data-action="add-attachment">' . htmlspecialchars(tr('+ Attachment')) . '</button>' . '<button class="fb-cta" data-action="add-link">' . htmlspecialchars(tr('+ Link')) . '</button>' . '</div>' . '</div>' . '</section>';
      $c++;
    }

    return
      '<div>' . '<header class="admin-page-header">' . '<h3>' . htmlspecialchars(tr('Flags Management')) . '</h3>' . '<span class="admin-section--status">' . htmlspecialchars(tr('status_')) . '<span class="highlighted">' . htmlspecialchars(tr('OK')) . '</span>' . '</span>' . '</header>' . $adminsections . '<div class="admin-buttons">' . '<button class="fb-cta" data-action="add-new">' . htmlspecialchars(tr('Add Flag Level')) . '</button>' . '</div>' . '</div>';
  }

  public function renderBasesContent(): string {
    list(
      $countries_select,
      $level_categories_select,
      $filter_categories_select,
    ) = [
      $this->generateCountriesSelect(0),
      $this->generateLevelCategoriesSelect(0),
      $this->generateFilterCategoriesSelect(),
    ];

    $adminsections =
      '<div class="admin-sections">' . '<section id="new-element" class="validate-form admin-box completely-hidden">' . '<form class="level_form base_form">' . '<input type="hidden" name="level_type" value="base" />' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('New Base Level')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-2">' . '<div class="form-el form-el--required el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Title')) . '</label>' . '<input name="title" type="text" placeholder="' . htmlspecialchars(tr('Level title')) . '" />' . '</div>' . '<div class="form-el form-el--required el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Description')) . '</label>' . '<textarea name="description" placeholder="' . htmlspecialchars(tr('Level description')) . '" rows="' . "4" . '">' . '</textarea>' . '</div>' . '<div class="form-el fb-column-container col-gutters">' . '<div class="col col-1-2 el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Country')) . '</label>' . $countries_select . '</div>' . '<div class="col col-1-2 el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Category')) . '</label>' . $level_categories_select . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-2">' . '<div class="form-el fb-column-container col-gutters">' . '<div class="form-el--required col col-1-2 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Keep Points')) . '</label>' . '<input name="points" type="text" />' . '</div>' . '<div class="form-el--required col col-1-2 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Capture points')) . '</label>' . '<input name="bonus" type="text" />' . '</div>' . '</div>' . '<div class="form-el fb-column-container col-gutters">' . '<div class="col col-2-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Hint')) . '</label>' . '<input name="hint" type="text" />' . '</div>' . '<div class="col col-1-3 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Hint Penalty')) . '</label>' . '<input name="penalty" type="text" />' . '</div>' . '</div>' . '</div>' . '</div>' . '<div class="admin-buttons admin-row">' . '<div class="button-right">' . '<a href="#" class="admin--edit" data-action="edit">' . htmlspecialchars(tr('EDIT')) . '</a>' . '<button class="fb-cta cta--red" data-action="delete">' . htmlspecialchars(tr('Delete')) . '</button>' . '<button class="fb-cta cta--yellow" data-action="create">' . htmlspecialchars(tr('Create')) . '</button>' . '</div>' . '</div>' . '</form>' . '</section>' . '<section id="new-element" class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('All Base Levels')) . '</h3>' . '<form class="all_base_form">' . '<div class="admin-section-toggle radio-inline col">' . '<input type="radio" name="fb--levels--all_base" id="fb--levels--all_base--on" />' . '<label for="fb--levels--all_base--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--levels--all_base" id="fb--levels--all_base--off" />' . '<label for="fb--levels--all_base--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</form>' . '</header>' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Filter By:')) . '</h3>' . '<div class="form-el fb-column-container col-gutters">' . '<div class="col col-1-5 el--block-label el--full-text">' . '</div>' . '<div class="col col-1-5 el--block-label el--full-text">' . $filter_categories_select . '</div>' . '<div class="col col-1-5 el--block-label el--full-text">' . '</div>' . '<div class="col col-1-5 el--block-label el--full-text">' . '<select class="not_configuration" name="status_filter">' . '<option class="filter_option" value="all">' . htmlspecialchars(tr('All Status')) . '</option>' . '<option class="filter_option" value="Enabled">' . htmlspecialchars(tr('Enabled')) . '</option>' . '<option class="filter_option" value="Disabled">' . htmlspecialchars(tr('Disabled')) . '</option>' . '</select>' . '</div>' . '<div class="col col-1-5 el--block-label el--full-text">' . '</div>' . '</div>' . '</header>' . '</section>' . '</div>';

    $c = 1;
    $all_base_levels = Level::allBaseLevels();
    foreach ($all_base_levels as $base) {
      $base_active_on = ($base->getActive());
      $base_active_off = (!$base->getActive());

      $base_status_name =
        'fb--levels--level-'.strval($base->getId()).'-status';
      $base_status_on_id =
        'fb--levels--level-'.strval($base->getId()).'-status--on';
      $base_status_off_id =
        'fb--levels--level-'.strval($base->getId()).'-status--off';

      $base_id = strval($base->getId());
      $base_id_txt = 'base_id'.strval($base->getId());

      $delete_button =
        '<div style="display: inline">' . '<input type="hidden" name="level_id" value="' . htmlspecialchars($base_id) . '" />' . '<a href="#" class="fb-cta cta--red js-delete-level" style="margin-right: 20px">' . htmlspecialchars(tr('Delete')) . '</a>' . '</div>';

      $attachments_div =
        '<div class="attachments">' . '<div class="new-attachment new-attachment-hidden fb-column-container completely-hidden">' . '<div class="col col-pad col-1-3">' . '<div class="form-el">' . '<form class="attachment_form">' . '<input type="hidden" name="action" value="create_attachment" />' . '<input type="hidden" name="level_id" value="' . htmlspecialchars(strval($base->getId())) . '" />' . '<div class="col el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('New Attachment:')) . '</label>' . '<input name="filename" type="text" />' . '<input name="attachment_file" type="file" />' . '</div>' . '</form>' . '</div>' . '</div>' . '<div class="admin-buttons col col-pad col-1-3">' . '<div class="col el--block-label el--full-text">' . '<button class="fb-cta cta--red" data-action="delete-new-attachment">' . 'X' . '</button>' . '<button class="fb-cta cta--yellow" data-action="create-attachment">' . htmlspecialchars(tr('Create')) . '</button>' . '</div>' . '</div>' . '</div>' . '</div>';
      $has_attachments = Attachment::hasAttachments($base->getId()); // TODO: Combine Awaits
      if ($has_attachments) {
        $a_c = 1;
        $all_attachments =
          Attachment::allAttachments($base->getId()); // TODO: Combine Awaits
        foreach ($all_attachments as $attachment) {
          $attachments_div .= '<div class="existing-attachment fb-column-container">' . '<div class="col col-pad col-2-3">' . '<div class="form-el">' . '<form class="attachment_form">' . '<input type="hidden" name="attachment_id" value="' . htmlspecialchars(strval($attachment->getId())) . '" />' . '<div class="col el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Attachment')) . htmlspecialchars((string)$a_c) . ':' . '</label>' . '<input name="filename" type="text" value="' . htmlspecialchars($attachment->getFilename()) . '" disabled />' . '<a href="' . htmlspecialchars($attachment->getFileLink()) . '" target="_blank">' . htmlspecialchars(tr('Link')) . '</a>' . '</div>' . '</form>' . '</div>' . '</div>' . '<div class="admin-buttons col col-pad col-1-3">' . '<div class="col el--block-label el--full-text">' . '<button class="fb-cta cta--red" data-action="delete-attachment">' . 'X' . '</button>' . '</div>' . '</div>' . '</div>';
        }
        $a_c++;
      }

      $links_div =
        '<div class="links">' . '<div class="new-link new-link-hidden fb-column-container completely-hidden">' . '<div class="col col-pad col-1-3">' . '<div class="form-el">' . '<form class="link_form">' . '<input type="hidden" name="action" value="create_link" />' . '<input type="hidden" name="level_id" value="' . htmlspecialchars(strval($base->getId())) . '" />' . '<div class="col el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('New Link:')) . '</label>' . '<input name="link" type="text" />' . '</div>' . '</form>' . '</div>' . '</div>' . '<div class="admin-buttons col col-pad col-1-3">' . '<div class="col el--block-label el--full-text">' . '<button class="fb-cta cta--red" data-action="delete-new-link">' . 'X' . '</button>' . '<button class="fb-cta cta--yellow" data-action="create-link">' . htmlspecialchars(tr('Create')) . '</button>' . '</div>' . '</div>' . '</div>' . '</div>';

      $has_links = Link::hasLinks($base->getId()); // TODO: Combine Awaits
      if ($has_links) {
        $l_c = 1;
        $all_links = Link::allLinks($base->getId()); // TODO: Combine Awaits
        foreach ($all_links as $link) {
          if (filter_var($link->getLink(), FILTER_VALIDATE_URL)) {
            $link_a =
              '<a href="' . htmlspecialchars($link->getLink()) . '" target="_blank">' . htmlspecialchars(tr('Link')) . '</a>';
          } else {
            $link_a = '<a>' . '</a>';
          }
          $links_div .= '<div class="existing-link fb-column-container">' . '<div class="col col-pad col-2-3">' . '<div class="form-el">' . '<form class="link_form">' . '<input type="hidden" name="link_id" value="' . htmlspecialchars(strval($link->getId())) . '" />' . '<div class="col el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Link')) . htmlspecialchars((string)$l_c) . ':' . '</label>' . '<input name="link" type="text" value="' . htmlspecialchars($link->getLink()) . '" disabled />' . $link_a . '</div>' . '</form>' . '</div>' . '</div>' . '<div class="admin-buttons col col-pad col-1-3">' . '<div class="col el--block-label el--full-text">' . '<button class="fb-cta cta--red" data-action="delete-link">' . 'X' . '</button>' . '</div>' . '</div>' . '</div>';
        }
        $l_c++;
      }

      list($countries_select, $level_categories_select) = [
        $this->generateCountriesSelect($base->getEntityId()),
        $this->generateLevelCategoriesSelect($base->getCategoryId()),
      ]; // TODO: Combine Awaits

      $adminsections .= '<section class="validate-form admin-box section-locked">' . '<form class="level_form base_form" name="' . htmlspecialchars($base_id_txt) . '">' . '<input type="hidden" name="level_type" value="base" />' . '<input type="hidden" name="level_id" value="' . htmlspecialchars(strval($base->getId())) . '" />' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Base Level')) . htmlspecialchars((string)$c) . '</h3>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="' . htmlspecialchars($base_status_name) . '" id="' . htmlspecialchars($base_status_on_id) . '" ' . ($base_active_on ? ' checked' : '') . ' />' . '<label for="' . htmlspecialchars($base_status_on_id) . '">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="' . htmlspecialchars($base_status_name) . '" id="' . htmlspecialchars($base_status_off_id) . '" ' . ($base_active_off ? ' checked' : '') . ' />' . '<label for="' . htmlspecialchars($base_status_off_id) . '">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-2">' . '<div class="form-el form-el--required el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Title')) . '</label>' . '<input name="title" type="text" value="' . htmlspecialchars($base->getTitle()) . '" disabled />' . '</div>' . '<div class="form-el form-el--required el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Description')) . '</label>' . '<textarea name="description" rows="' . "4" . '" disabled>' . htmlspecialchars($base->getDescription()) . '</textarea>' . '</div>' . '<div class="form-el form-el--required fb-column-container col-gutters">' . '<div class="col col-1-2 el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Country')) . '</label>' . $countries_select . '</div>' . '<div class="col col-1-2 el--block-label el--full-text">' . '<label for="">' . htmlspecialchars(tr('Category')) . '</label>' . $level_categories_select . '</div>' . '</div>' . '</div>' . '<div class="col col-pad col-1-2">' . '<div class="form-el fb-column-container col-gutters">' . '<div class="form-el--required col col-1-2 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Points')) . '</label>' . '<input name="points" type="text" value="' . htmlspecialchars(strval($base->getPoints())) . '" disabled />' . '</div>' . '<div class="col col-1-2 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Bonus')) . '</label>' . '<input name="bonus" type="text" value="' . htmlspecialchars(strval($base->getBonus())) . '" disabled />' . '</div>' . '</div>' . '<div class="form-el fb-column-container col-gutters">' . '<div class="col col-1-2 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Hint')) . '</label>' . '<input name="hint" type="text" value="' . htmlspecialchars($base->getHint()) . '" disabled />' . '</div>' . '<div class="col col-1-2 el--block-label el--full-text">' . '<label>' . htmlspecialchars(tr('Hint Penalty')) . '</label>' . '<input name="penalty" type="text" value="' . htmlspecialchars(strval($base->getPenalty())) . '" disabled />' . '</div>' . '</div>' . '</div>' . '</div>' . '</form>' . $attachments_div . $links_div . '<div class="admin-buttons admin-row">' . '<div class="button-right">' . '<a href="#" class="admin--edit" data-action="edit">' . htmlspecialchars(tr('EDIT')) . '</a>' . $delete_button . '<button class="fb-cta cta--yellow" data-action="save">' . htmlspecialchars(tr('Save')) . '</button>' . '</div>' . '<div class="button-left">' . '<button class="fb-cta" data-action="add-attachment">' . htmlspecialchars(tr('+ Attachment')) . '</button>' . '<button class="fb-cta" data-action="add-link">' . htmlspecialchars(tr('+ Link')) . '</button>' . '</div>' . '</div>' . '</section>';
      $c++;
    }

    return
      '<div>' . '<header class="admin-page-header">' . '<h3>' . htmlspecialchars(tr('Bases Management')) . '</h3>' . '<span class="admin-section--status">' . htmlspecialchars(tr('status_')) . '<span class="highlighted">' . htmlspecialchars(tr('OK')) . '</span>' . '</span>' . '</header>' . $adminsections . '<div class="admin-buttons">' . '<button class="fb-cta" data-action="add-new">' . htmlspecialchars(tr('Add Base Level')) . '</button>' . '</div>' . '</div>';
  }

  public function renderCategoriesContent(): string {
    $adminsections = '<div class="admin-sections">';

    $adminsections .= '<section class="admin-box completely-hidden">' . '<form class="categories_form">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('New Category')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad">' . '<div class="form-el el--block-label el--full-text">' . '<label class="admin-label" for="">' . htmlspecialchars(tr('Category')) . ':' . '</label>' . '<input name="category" type="text" value="" />' . '</div>' . '</div>' . '</div>' . '<div class="admin-buttons admin-row">' . '<div class="button-right">' . '<button class="fb-cta cta--red" data-action="delete">' . htmlspecialchars(tr('Delete')) . '</button>' . '<button class="fb-cta cta--yellow" data-action="create">' . htmlspecialchars(tr('Create')) . '</button>' . '</div>' . '</div>' . '</form>' . '</section>';

    $categories = Category::allCategories();

    foreach ($categories as $category) {
      if ($category->getProtected()) {
        $category_name =
          '<span class="logo-name">' . htmlspecialchars($category->getCategory()) . '</span>';
      } else {
        $category_name =
          '<div>' . '<input name="category" type="text" value="' . htmlspecialchars($category->getCategory()) . '" />' . '<a class="highlighted--yellow" href="#" data-action="save-category">' . htmlspecialchars(tr('Save')) . '</a>' . '</div>';
      }

      $is_used = Category::isUsed($category->getId()); // TODO: Combine Awaits
      ;
      if ($is_used || $category->getProtected()) {
        $delete_action = '<a>' . '</a>';
      } else {
        $delete_action =
          '<a class="highlighted--red" href="#" data-action="delete">' . htmlspecialchars(tr('DELETE')) . '</a>';
      }
      $adminsections .= '<section class="admin-box">' . '<form class="categories_form">' . '<input type="hidden" name="category_id" value="' . htmlspecialchars(strval($category->getId())) . '" />' . '<header class="management-header">' . '<h6>' . 'ID' . htmlspecialchars(strval($category->getId())) . '</h6>' . $delete_action . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad">' . '<div class="category">' . '<label>' . htmlspecialchars(tr('Category: ')) . '</label>' . $category_name . '</div>' . '</div>' . '</div>' . '</form>' . '</section>';
    }

    return
      '<div>' . '<header class="admin-page-header">' . '<h3>' . htmlspecialchars(tr('Categories Management')) . '</h3>' . '<span class="admin-section--status">' . htmlspecialchars(tr('status_')) . '<span class="highlighted">' . htmlspecialchars(tr('OK')) . '</span>' . '</span>' . '</header>' . $adminsections . '<div class="admin-buttons">' . '<button class="fb-cta" data-action="add-new">' . htmlspecialchars(tr('Add Category')) . '</button>' . '</div>' . '</div>';
  }

  public function renderCountriesContent(): string {
    $adminsections = '<div class="admin-sections">' . '</div>';

    $adminsections .= '<section id="new-element" class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Filter By:')) . '</h3>' . '<div class="form-el fb-column-container col-gutters">' . '<div class="col col-1-5 el--block-label el--full-text">' . '</div>' . '<div class="col col-1-5 el--block-label el--full-text">' . '<select class="not_configuration" name="use_filter">' . '<option class="filter_option" value="all">' . htmlspecialchars(tr('All Countries')) . '</option>' . '<option class="filter_option" value="Yes">' . htmlspecialchars(tr('In Use')) . '</option>' . '<option class="filter_option" value="No">' . htmlspecialchars(tr('Not Used')) . '</option>' . '</select>' . '</div>' . '<div class="col col-1-5 el--block-label el--full-text">' . '</div>' . '<div class="col col-1-5 el--block-label el--full-text">' . '<select class="not_configuration" name="country_status_filter">' . '<option class="filter_option" value="all">' . htmlspecialchars(tr('All Status')) . '</option>' . '<option class="filter_option" value="enabled">' . htmlspecialchars(tr('Enabled')) . '</option>' . '<option class="filter_option" value="disabled">' . htmlspecialchars(tr('Disabled')) . '</option>' . '</select>' . '</div>' . '<div class="col col-1-5 el--block-label el--full-text">' . '</div>' . '</div>' . '</header>' . '</section>';

    $all_countries = Country::allCountries();
    foreach ($all_countries as $country) {
      $using_country = Level::whoUses($country->getId()); // TODO: Combine Awaits
      $current_use = ($using_country) ? tr('Yes') : tr('No');
      if ($country->getEnabled()) {
        $highlighted_action = 'disable_country';
        $highlighted_color = 'highlighted--red country-enabled';
        $current_status = 'Disabled';
      } else {
        $highlighted_action = 'enable_country';
        $highlighted_color = 'highlighted--green country-disabled';
        $current_status = 'Enabled';
      }

      if (!$using_country) {
        $status_action =
          '<a class="' . htmlspecialchars($highlighted_color) . '" href="#" data-action="' . htmlspecialchars(str_replace('_', '-', $highlighted_action)) . '">' . htmlspecialchars(tr($current_status)) . '</a>';
      } else {
        $status_action = '<a class="' . htmlspecialchars($highlighted_color) . '">' . '</a>';
      }

      $adminsections .= '<section class="admin-box">' . '<form class="country_form">' . '<input type="hidden" name="country_id" value="' . htmlspecialchars(strval($country->getId())) . '" />' . '<input type="hidden" name="status_action" value="' . htmlspecialchars($highlighted_action) . '" />' . '<header class="management-header">' . '<h6>' . 'ID' . htmlspecialchars(strval($country->getId())) . '</h6>' . $status_action . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-2-3">' . '<div class="selected-logo">' . '<label>' . htmlspecialchars(tr('Country')) . ':' . '</label>' . '<span class="logo-name">' . htmlspecialchars($country->getName()) . '</span>' . '</div>' . '</div>' . '<div class="col col-pad col-1-3">' . '<div class="selected-logo">' . '<label>' . htmlspecialchars(tr('ISO Code')) . ':' . '</label>' . '<span class="logo-name">' . htmlspecialchars($country->getIsoCode()) . '</span>' . '</div>' . '<div class="selected-logo">' . '<label>' . htmlspecialchars(tr('In Use')) . ':' . '</label>' . '<span class="logo-name country-use">' . htmlspecialchars($current_use) . '</span>' . '</div>' . '</div>' . '</div>' . '</form>' . '</section>';
    }
    return
      '<div>' . '<header class="admin-page-header">' . '<h3>' . htmlspecialchars(tr('Countries Management')) . '</h3>' . '<span class="admin-section--status">' . htmlspecialchars(tr('status_')) . '<span class="highlighted">' . htmlspecialchars(tr('OK')) . '</span>' . '</span>' . '</header>' . $adminsections . '</div>';
  }

  private function generateTeamNames(int $team_id): string {
    $names = '<section class="admin-box">';

    $teams_data = Team::teamData($team_id);

    if (count($teams_data) > 0) {
      foreach ($teams_data as $data) {
        $names .= '<div class="fb-column-container">' . '<div class="col col-pad col-2-3">' . '<div class="form-el el--block-label el--full-text">' . '<label class="admin-label" for="">' . htmlspecialchars(tr('Name')) . '</label>' . '<input name="name" type="text" value="' . htmlspecialchars($data['name']) . '" disabled />' . '</div>' . '</div>' . '<div class="col col-pad col-2-3">' . '<div class="form-el el--block-label el--full-text">' . '<label class="admin-label" for="">' . htmlspecialchars(tr('Email')) . '</label>' . '<input name="email" type="text" value="' . htmlspecialchars($data['email']) . '" disabled />' . '</div>' . '</div>' . '</div>';
      }
    } else {
      $names .= '<div class="fb-column-container">' . '<div class="col col-pad">' . htmlspecialchars(tr('No Team Names')) . '</div>' . '</div>';
      $names .= '</section>';
    }

    return $names;
  }

  private function generateTeamScores(int $team_id): string {
    $scores_div = '<div>';
    $scores = ScoreLog::allScoresByTeam($team_id, true);
    if (count($scores) > 0) {
      $scores_tbody = '<tbody>';
      foreach ($scores as $score) {
        $level = Level::get($score->getLevelId()); // TODO: Combine Awaits
        $country = Country::get($level->getEntityId()); // TODO: Combine Awaits
        $level_str = $country->getName().' - '.$level->getTitle();
        $scores_tbody .= '<tr>' . '<td style="width: 20%;">' . htmlspecialchars(time_ago($score->getTs())) . '</td>' . '<td style="width: 13%;">' . htmlspecialchars($score->getType()) . '</td>' . '<td style="width: 7%;">' . htmlspecialchars(strval($score->getPoints())) . '</td>' . '<td style="width: 60%;">' . htmlspecialchars($level_str) . '</td>' . '</tr>';
      }
      $scores_tbody .= '</tbody>';
      $scores_div .= '<table>' . '<thead>' . '<tr>' . '<th style="width: 20%;">' . htmlspecialchars(tr('time')) . '_' . '</th>' . '<th style="width: 13%;">' . htmlspecialchars(tr('type')) . '_' . '</th>' . '<th style="width: 7%;">' . htmlspecialchars(tr('pts')) . '_' . '</th>' . '<th style="width: 60%;">' . htmlspecialchars(tr('Level')) . '_' . '</th>' . '</tr>' . '</thead>' . $scores_tbody . '</table>';
    } else {
      $scores_div .= '<div class="fb-column-container">' . '<div class="col col-pad">' . htmlspecialchars(tr('No Scores')) . '</div>' . '</div>';
      $scores_div .= '</div>';
    }

    return $scores_div;
  }

  private function generateTeamFailures(
    int $team_id,
  ): string {
    $failures_div = '<div>';
    $failures = FailureLog::allFailuresByTeam($team_id);
    if (count($failures) > 0) {
      $failures_tbody = '<tbody>';
      foreach ($failures as $failure) {
        $check_status = Level::checkStatus($failure->getLevelId()); // TODO: Combine Awaits
        if (!$check_status) {
          continue;
        }
        $level = Level::get($failure->getLevelId());
        $country = Country::get($level->getEntityId());
        $level_str = $country->getName().' - '.$level->getTitle();
        $failures_tbody .= '<tr>' . '<td style="width: 20%;">' . htmlspecialchars(time_ago($failure->getTs())) . '</td>' . '<td style="width: 40%;">' . htmlspecialchars($level_str) . '</td>' . '<td style="width: 40%;">' . htmlspecialchars($failure->getFlag()) . '</td>' . '</tr>';
      }
      $failures_tbody .= '</tbody>';
      $failures_div .= '<table>' . '<thead>' . '<tr>' . '<th style="width: 20%;">' . htmlspecialchars(tr('time')) . '_' . '</th>' . '<th style="width: 40%;">' . htmlspecialchars(tr('Level')) . '_' . '</th>' . '<th style="width: 40%;">' . htmlspecialchars(tr('Attempt')) . '_' . '</th>' . '</tr>' . '</thead>' . $failures_tbody . '</table>';
    } else {
      $failures_div .= '<div class="fb-column-container">' . '<div class="col col-pad">' . htmlspecialchars(tr('No Failures')) . '</div>' . '</div>';
      $failures_div .= '</div>';
    }

    return $failures_div;
  }

  private function generateTeamTabs(int $team_id): string {
    $team_tabs_team = 'fb--teams--tabs--team-team'.strval($team_id);
    $team_tabs_names = 'fb--teams--tabs--names-team'.strval($team_id);
    $team_tabs_scores = 'fb--teams--tabs--scores-team'.strval($team_id);
    $team_tabs_failures = 'fb--teams--tabs--failures-team'.strval($team_id);
    $team_tabs_name = 'fb--teams--tabs-team'.strval($team_id);
    $tab_team = 'team'.strval($team_id);
    $tab_names = 'names'.strval($team_id);
    $tab_scores = 'scores'.strval($team_id);
    $tab_failures = 'failures'.strval($team_id);

    $team_tabs = '<div class="radio-tabs">';
    $team_tabs .= '<input type="radio" value="' . htmlspecialchars($tab_team) . '" name="' . htmlspecialchars($team_tabs_name) . '" id="' . htmlspecialchars($team_tabs_team) . '" checked />';
    $team_tabs .= '<label for="' . htmlspecialchars($team_tabs_team) . '">' . htmlspecialchars(tr('Team')) . '</label>';

    $registration_names = Configuration::get('registration_names');
    if ($registration_names->getValue() === '1') {
      $team_tabs .= '<input type="radio" value="' . htmlspecialchars($tab_names) . '" name="' . htmlspecialchars($team_tabs_name) . '" id="' . htmlspecialchars($team_tabs_names) . '" />';
      $team_tabs .= '<label for="' . htmlspecialchars($team_tabs_names) . '">' . htmlspecialchars(tr('Names')) . '</label>';
    }

    $team_tabs .= '<input type="radio" value="' . htmlspecialchars($tab_scores) . '" name="' . htmlspecialchars($team_tabs_name) . '" id="' . htmlspecialchars($team_tabs_scores) . '" />';
    $team_tabs .= '<label for="' . htmlspecialchars($team_tabs_scores) . '">' . htmlspecialchars(tr('Scores')) . '</label>';

    $team_tabs .= '<input type="radio" value="' . htmlspecialchars($tab_failures) . '" name="' . htmlspecialchars($team_tabs_name) . '" id="' . htmlspecialchars($team_tabs_failures) . '" />';
    $team_tabs .= '<label for="' . htmlspecialchars($team_tabs_failures) . '">' . htmlspecialchars(tr('Failures')) . '</label>';
    $team_tabs .= '</div>';

    return $team_tabs;
  }

  public function renderTeamsContent(): string {
    $adminsections =
      '<div class="admin-sections">' . '<section class="admin-box validate-form section-locked completely-hidden">' . '<form class="team_form">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('New Team')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-2">' . '<div class="form-el--required el--block-label el--full-text">' . '<label class="admin-label" for="">' . htmlspecialchars(tr('Team Name')) . '</label>' . '<input name="team_name" type="text" value="" maxlength="' . "20" . '" />' . '</div>' . '</div>' . '<div class="col col-pad col-1-2">' . '<div class="form-el--required el--block-label el--full-text">' . '<label class="admin-label" for="">' . htmlspecialchars(tr('Password')) . '</label>' . '<input name="password" type="password" value="" />' . '</div>' . '</div>' . '</div>' . '<div class="admin-row el--block-label">' . '<label>' . htmlspecialchars(tr('Team Logo')) . '</label>' . '<div class="fb-column-container">' . '<div class="col col-shrink">' . '<div class="post-avatar has-avatar">' . '<svg class="icon icon--badge">' . '<use href="#icon--badge-" />' . '</svg>' . '</div>' . '</div>' . '<div class="form-el--required col col-grow">' . '<div class="selected-logo">' . '<label>' . htmlspecialchars(tr('Selected Logo:')) . '</label>' . '<span class="logo-name">' . '</span>' . '</div>' . '<a href="#" class="alt-link js-choose-logo">' . htmlspecialchars(tr('Select Logo')) . '</a>' . '</div>' . '<div class="col col-shrink admin-buttons">' . '<a href="#" class="admin--edit" data-action="edit">' . htmlspecialchars(tr('EDIT')) . '</a>' . '<button class="fb-cta cta--red" data-action="delete">' . htmlspecialchars(tr('Delete')) . '</button>' . '<button class="fb-cta cta--yellow js-confirm-save" data-action="create">' . htmlspecialchars(tr('Create')) . '</button>' . '</div>' . '</div>' . '</div>' . '</form>' . '</section>' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('All Teams')) . '</h3>' . '<form class="all_team_form">' . '<div class="admin-section-toggle radio-inline col">' . '<input type="radio" name="fb--teams--all_team" id="fb--teams--all_team--on" />' . '<label for="fb--teams--all_team--on">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="fb--teams--all_team" id="fb--teams--all_team--off" />' . '<label for="fb--teams--all_team--off">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</form>' . '</header>' . '</section>' . '</div>';

    $c = 1;
    $all_teams = Team::allTeams();
    foreach ($all_teams as $team) {
      $logo_model = $team->getLogoModel(); // TODO: Combine Awaits
      if ($logo_model->getCustom()) {
        $image = '<img class="icon--badge" src="' . htmlspecialchars($logo_model->getLogo()) . '">' . '</img>';
      } else {
        $iconbadge = '#icon--badge-'.$logo_model->getName();
        $image =
          '<svg class="icon--badge">' . '<use href="' . htmlspecialchars($iconbadge) . '" />' . '</svg>';
      }

      $team_protected = $team->getProtected();
      $team_active_on = $team->getActive();
      $team_active_off = !$team->getActive();
      $team_admin_on = $team->getAdmin();
      $team_admin_off = !$team->getAdmin();
      $team_visible_on = $team->getVisible();
      $team_visible_off = !$team->getVisible();
      $team_id = strval($team->getId());

      $team_status_name = 'fb--teams--team-'.strval($team->getId()).'-status';
      $team_status_on_id =
        'fb--teams--team-'.strval($team->getId()).'-status--on';
      $team_status_off_id =
        'fb--teams--team-'.strval($team->getId()).'-status--off';
      $team_admin_name = 'fb--teams--team-'.strval($team->getId()).'-admin';
      $team_admin_on_id =
        'fb--teams--team-'.strval($team->getId()).'-admin--on';
      $team_admin_off_id =
        'fb--teams--team-'.strval($team->getId()).'-admin--off';
      $team_visible_name =
        'fb--teams--team-'.strval($team->getId()).'-visible';
      $team_visible_on_id =
        'fb--teams--team-'.strval($team->getId()).'-visible--on';
      $team_visible_off_id =
        'fb--teams--team-'.strval($team->getId()).'-visible--off';

      if ($team_protected) {
        $toggle_status =
          '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="' . htmlspecialchars($team_status_name) . '" id="' . htmlspecialchars($team_status_on_id) . '" ' . ($team_active_on ? ' checked' : '') . ' />' . '<label for="' . htmlspecialchars($team_status_on_id) . '">' . htmlspecialchars(tr('On')) . '</label>' . '</div>';
        $toggle_admin =
          '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="' . htmlspecialchars($team_admin_name) . '" id="' . htmlspecialchars($team_admin_on_id) . '" ' . ($team_admin_on ? ' checked' : '') . ' />' . '<label for="' . htmlspecialchars($team_admin_on_id) . '">' . htmlspecialchars(tr('On')) . '</label>' . '</div>';
        $delete_button =
          '<button class="fb-cta cta--red" disabled>' . htmlspecialchars(tr('Protected')) . '</button>';
      } else {
        $toggle_status =
          '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="' . htmlspecialchars($team_status_name) . '" id="' . htmlspecialchars($team_status_on_id) . '" ' . ($team_active_on ? ' checked' : '') . ' />' . '<label for="' . htmlspecialchars($team_status_on_id) . '">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="' . htmlspecialchars($team_status_name) . '" id="' . htmlspecialchars($team_status_off_id) . '" ' . ($team_active_off ? ' checked' : '') . ' />' . '<label for="' . htmlspecialchars($team_status_off_id) . '">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>';
        $toggle_admin =
          '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="' . htmlspecialchars($team_admin_name) . '" id="' . htmlspecialchars($team_admin_on_id) . '" ' . ($team_admin_on ? ' checked' : '') . ' />' . '<label for="' . htmlspecialchars($team_admin_on_id) . '">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="' . htmlspecialchars($team_admin_name) . '" id="' . htmlspecialchars($team_admin_off_id) . '" ' . ($team_admin_off ? ' checked' : '') . ' />' . '<label for="' . htmlspecialchars($team_admin_off_id) . '">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>';
        $delete_button =
          '<div style="display: inline">' . '<input type="hidden" name="team_id" value="' . htmlspecialchars($team_id) . '" />' . '<a href="#" class="fb-cta cta--red js-delete-team" style="margin-right: 20px">' . htmlspecialchars(tr('Delete')) . '</a>' . '</div>';
      }

      $tab_team = 'team'.strval($team->getId());
      $tab_names = 'names'.strval($team->getId());
      $tab_scores = 'scores'.strval($team->getId());
      $tab_failures = 'failures'.strval($team->getId());

      $awaitables = [
        'team_tabs' => $this->generateTeamTabs($team->getId()),
        'team_names' => $this->generateTeamNames($team->getId()),
        'team_scores' => $this->generateTeamScores($team->getId()),
        'team_failures' => $this->generateTeamFailures($team->getId()),
      ];

      $results = $awaitables; // TODO: Combine Awaits

      $team_tabs = $results['team_tabs'];
      $team_names = $results['team_names'];
      $team_scores = $results['team_scores'];
      $team_failures = $results['team_failures'];

      $adminsections .= '<div>' . $team_tabs . '<div class="tab-content-container">' . '<div class="radio-tab-content active" data-tab="' . htmlspecialchars($tab_team) . '">' . '<section class="admin-box validate-form section-locked">' . '<form class="team_form" name="' . htmlspecialchars(strval($team->getId())) . '">' . '<input type="hidden" name="team_id" value="' . htmlspecialchars(strval($team->getId())) . '" />' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Team')) . htmlspecialchars((string)$c) . '</h3>' . $toggle_status . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-1-3">' . '<div class="form-el form-el--required el--block-label el--full-text">' . '<label class="admin-label" for="">' . htmlspecialchars(tr('Team Name')) . '</label>' . '<input name="team_name" type="text" value="' . htmlspecialchars($team->getName()) . '" maxlength="' . "20" . '" disabled />' . '</div>' . '<div class="form-el form-el--required el--block-label el--full-text">' . '<label class="admin-label" for="">' . htmlspecialchars(tr('Score')) . '</label>' . '<input name="points" type="text" value="' . htmlspecialchars(strval($team->getPoints())) . '" disabled />' . '</div>' . '</div>' . '<div class="col col-pad col-1-3">' . '<div class="form-el el--block-label el--full-text">' . '<label class="admin-label" for="">' . htmlspecialchars(tr('Change Password')) . '</label>' . '<input name="password" type="password" disabled />' . '</div>' . '</div>' . '<div class="col col-pad col-1-3">' . '<div class="form-el el--block-label">' . '<label class="admin-label" for="">' . htmlspecialchars(tr('Admin Level')) . '</label>' . $toggle_admin . '</div>' . '<div class="form-el el--block-label">' . '<label class="admin-label" for="">' . htmlspecialchars(tr('Visibility')) . '</label>' . '<div class="admin-section-toggle radio-inline">' . '<input type="radio" name="' . htmlspecialchars($team_visible_name) . '" id="' . htmlspecialchars($team_visible_on_id) . '" ' . ($team_visible_on ? ' checked' : '') . ' />' . '<label for="' . htmlspecialchars($team_visible_on_id) . '">' . htmlspecialchars(tr('On')) . '</label>' . '<input type="radio" name="' . htmlspecialchars($team_visible_name) . '" id="' . htmlspecialchars($team_visible_off_id) . '" ' . ($team_visible_off ? ' checked' : '') . ' />' . '<label for="' . htmlspecialchars($team_visible_off_id) . '">' . htmlspecialchars(tr('Off')) . '</label>' . '</div>' . '</div>' . '</div>' . '</div>' . '<div class="admin-row el--block-label">' . '<label>' . htmlspecialchars(tr('Team Logo')) . '</label>' . '<div class="fb-column-container">' . '<div class="col col-shrink">' . '<div class="post-avatar has-avatar">' . $image . '</div>' . '</div>' . '<div class="form-el--required col col-grow">' . '<div class="selected-logo">' . '<label>' . htmlspecialchars(tr('Selected Logo:')) . '</label>' . '<span class="logo-name">' . htmlspecialchars($team->getLogo()) . '</span>' . '</div>' . '<a href="#" class="alt-link js-choose-logo">' . htmlspecialchars(tr('Select Logo')) . '</a>' . '</div>' . '<div class="col col-shrink admin-buttons">' . '<a href="#" class="admin--edit" data-action="edit">' . htmlspecialchars(tr('EDIT')) . '</a>' . $delete_button . '<button class="fb-cta cta--yellow js-confirm-save" data-action="save">' . htmlspecialchars(tr('Save')) . '</button>' . '</div>' . '</div>' . '</div>' . '</form>' . '</section>' . '</div>' . '<div class="radio-tab-content" data-tab="' . htmlspecialchars($tab_names) . '">' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Team')) . htmlspecialchars((string)$c) . '</h3>' . '</header>' . $team_names . '</section>' . '</div>' . '<div class="radio-tab-content" data-tab="' . htmlspecialchars($tab_scores) . '">' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Team')) . htmlspecialchars((string)$c) . '</h3>' . '</header>' . $team_scores . '</section>' . '</div>' . '<div class="radio-tab-content" data-tab="' . htmlspecialchars($tab_failures) . '">' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Team')) . htmlspecialchars((string)$c) . '</h3>' . '</header>' . $team_failures . '</section>' . '</div>' . '</div>' . '</div>';
      $c++;
    }
    return
      '<div>' . '<header class="admin-page-header">' . '<h3>' . htmlspecialchars(tr('Team Management')) . '</h3>' . '<span class="admin-section--status">' . ' status_' . '<span class="highlighted">' . htmlspecialchars(tr('OK')) . '</span>' . '</span>' . '</header>' . $adminsections . '<div class="admin-buttons">' . '<button class="fb-cta" data-action="add-new">' . htmlspecialchars(tr('Add Team')) . '</button>' . '</div>' . '</div>';
  }

  public function renderLogosContent(): string {
    $adminsections = '<div class="admin-sections">' . '</div>';

    $all_logos = Logo::allLogos();

    foreach ($all_logos as $logo) {
      if ($logo->getCustom()) {
        $image = '<img class="icon--badge" src="' . htmlspecialchars($logo->getLogo()) . '">' . '</img>';
      } else {
        $iconbadge = '#icon--badge-'.$logo->getName();
        $image =
          '<svg class="icon--badge">' . '<use href="' . htmlspecialchars($iconbadge) . '" />' . '</svg>';
      }
      $using_logo = MultiTeam::whoUses($logo->getName()); // TODO: Combine Awaits
      $current_use = (count($using_logo) > 0) ? tr('Yes') : tr('No');
      if ($logo->getEnabled()) {
        $highlighted_action = 'disable_logo';
        $highlighted_color = 'highlighted--red';
      } else {
        $highlighted_action = 'enable_logo';
        $highlighted_color = 'highlighted--green';
      }
      $action_text = strtoupper(explode('_', $highlighted_action)[0]);

      if ($using_logo) {
        $use_select = '<select class="not_configuration">' . '</select>';
        foreach ($using_logo as $t) {
          $use_select .= '<option value="">' . htmlspecialchars($t->getName()) . '</option>';
        }
      } else {
        $use_select =
          '<select class="not_configuration">' . '<option value="0">' . htmlspecialchars(tr('None')) . '</option>' . '</select>';
      }

      $adminsections .= '<section class="admin-box">' . '<form class="logo_form">' . '<input type="hidden" name="logo_id" value="' . htmlspecialchars(strval($logo->getId())) . '" />' . '<input type="hidden" name="status_action" value="' . htmlspecialchars(strtolower($action_text)) . '" />' . '<header class="management-header">' . '<h6>' . 'ID' . htmlspecialchars(strval($logo->getId())) . '</h6>' . '<a class="' . htmlspecialchars($highlighted_color) . '" href="#" data-action="' . htmlspecialchars(str_replace('_', '-', $highlighted_action)) . '">' . htmlspecialchars($action_text) . '</a>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-pad col-shrink">' . '<div class="post-avatar has-avatar">' . $image . '</div>' . '</div>' . '<div class="col col-pad col-grow">' . '<div class="selected-logo">' . '<label>' . htmlspecialchars(tr('Logo Name')) . ':' . '</label>' . '<span class="logo-name">' . htmlspecialchars($logo->getName()) . '</span>' . '</div>' . '<div class="selected-logo">' . '<label>' . htmlspecialchars(tr('In use')) . ':' . '</label>' . '<span class="logo-name">' . htmlspecialchars($current_use) . '</span>' . '</div>' . '</div>' . '<div class="col col-pad col-1-3">' . '<div class="form-el el--select el--block-label">' . '<label for="">' . htmlspecialchars(tr('Used By')) . ':' . '</label>' . $use_select . '</div>' . '</div>' . '</div>' . '</form>' . '</section>';
    }

    return
      '<div>' . '<header class="admin-page-header">' . '<h3>' . htmlspecialchars(tr('Logo Management')) . '</h3>' . '<span class="admin-section--status">' . htmlspecialchars(tr('status_')) . '<span class="highlighted">' . htmlspecialchars(tr('OK')) . '</span>' . '</span>' . '</header>' . $adminsections . '</div>';
  }

  public function renderSessionsContent(): string {
    $adminsections = '<div class="admin-sections">' . '</div>';

    $c = 1;
    $all_sessions = Session::allSessions();
    foreach ($all_sessions as $session) {
      $cookie = $_COOKIE['FBCTF'];
      if ($cookie === $session->getCookie()) {
        $session_data = Session::sessionDataIfExist($cookie); // TODO: Combine Awaits
        Session::setTeamId($cookie, $session_data); // TODO: Combine Awaits
        $session = Session::get($cookie); // TODO: Combine Awaits
      } else if ($session->getTeamId() === 0) {
        continue;
      }
      $session_id = 'session_'.strval($session->getId());
      $team = MultiTeam::team($session->getTeamId()); // TODO: Combine Awaits
      $adminsections .= '<section class="admin-box section-locked">' . '<form class="session_form" name="' . htmlspecialchars($session_id) . '">' . '<input type="hidden" name="session_id" value="' . htmlspecialchars(strval($session->getId())) . '" />' . '<header class="admin-box-header">' . '<span class="session-name">' . htmlspecialchars(tr('Session')) . htmlspecialchars((string)$c) . ': ' . '<span class="highlighted--blue">' . htmlspecialchars($team->getName()) . '</span>' . '</span>' . '</header>' . '<div class="fb-column-container">' . '<div class="col col-1-3 col-pad">' . '<div class="form-el el--block-label el--full-text">' . '<label class="admin-label">' . htmlspecialchars(tr('Cookie')) . '</label>' . '<input name="cookie" type="text" value="' . htmlspecialchars($session->getCookie()) . '" disabled />' . '</div>' . '</div>' . '<div class="col col-1-3 col-pad">' . '<div class="form-el el--block-label el--full-text">' . '<label class="admin-label">' . htmlspecialchars(tr('Creation Time')) . ':' . '</label>' . '<span class="highlighted">' . '<label class="admin-label">' . htmlspecialchars(time_ago($session->getCreatedTs())) . '</label>' . '</span>' . '</div>' . '</div>' . '<div class="col col-1-3 col-pad">' . '<div class="form-el el--block-label el--full-text">' . '<label class="admin-label">' . htmlspecialchars(tr('Last Access')) . ':' . '</label>' . '<span class="highlighted">' . '<label class="admin-label">' . htmlspecialchars(time_ago($session->getLastAccessTs())) . '</label>' . '</span>' . '</div>' . '</div>' . '<div class="col col-1-3 col-pad">' . '<div class="form-el el--block-label el--full-text">' . '<label class="admin-label">' . htmlspecialchars(tr('Last Page Access')) . ': ' . '</label>' . '<span class="highlighted">' . '<label class="admin-label">' . htmlspecialchars($session->getLastPageAccess()) . '</label>' . '</span>' . '</div>' . '</div>' . '</div>' . '<div class="admin-row">' . '<div class="form-el el--block-label el--full-text">' . '<label class="admin-label">' . htmlspecialchars(tr('Data')) . '</label>' . '<input name="data" type="text" value="' . htmlspecialchars($session->getData()) . '" disabled />' . '</div>' . '</div>' . '<div class="admin-buttons admin-row">' . '<div class="button-right">' . '<a href="#" class="admin--edit" data-action="edit">' . htmlspecialchars(tr('EDIT')) . '</a>' . '<button class="fb-cta cta--red" data-action="delete">' . htmlspecialchars(tr('Delete')) . '</button>' . '</div>' . '</div>' . '</form>' . '</section>';
      $adminsections .= '</div>';
      $c++;
    }
    return
      '<div>' . '<header class="admin-page-header">' . '<h3>' . htmlspecialchars(tr('Sessions')) . '</h3>' . '<span class="admin-section--status">' . htmlspecialchars(tr('status_')) . '<span class="highlighted">' . htmlspecialchars(tr('OK')) . '</span>' . '</span>' . '</header>' . $adminsections . '</div>';
  }

  public function renderLogsContent(): string {
    $gamelogs = GameLog::gameLog();

    if (count($gamelogs) > 0) {
      $logs_tbody = '<tbody>';
      $logs_table = '<div>' . '</div>';
      foreach ($gamelogs as $gamelog) {
        if ($gamelog->getEntry() === 'score') {
          $log_entry =
            '<span class="highlighted--green">' . htmlspecialchars($gamelog->getEntry()) . '</span>';
        } else {
          $log_entry =
            '<span class="highlighted--red">' . htmlspecialchars($gamelog->getEntry()) . '</span>';
        }

        list($team, $level) = [
          MultiTeam::team($gamelog->getTeamId()),
          Level::get($gamelog->getLevelId()),
        ]; // TODO: Combine Awaits

        if (!($team !== null)) { throw new \RuntimeException('Team should not be null'); }
        if (!($team instanceof Team)) { throw new \RuntimeException('team should be of type Team'); }

        if (!($level !== null)) { throw new \RuntimeException('Level should not be null'); }
        if (!($level instanceof Level)) { throw new \RuntimeException('level should be of type Level'); }

        $country = Country::get($level->getEntityId()); // TODO: Combine Awaits

        $team_name = $team->getName();

        $level_str =
          $country->getName().
          ' - '.
          $level->getTitle().
          ' - '.
          $level->getType();
        $logs_tbody .= '<tr>' . '<td>' . htmlspecialchars(time_ago($gamelog->getTs())) . '</td>' . '<td>' . $log_entry . '</td>' . '<td>' . htmlspecialchars($level_str) . '</td>' . '<td>' . htmlspecialchars(strval($gamelog->getPoints())) . '</td>' . '<td>' . htmlspecialchars($team_name) . '</td>' . '<td>' . htmlspecialchars($gamelog->getFlag()) . '</td>' . '</tr>';
        $logs_tbody .= '</tbody>';
        $logs_table =
          '<table>' . '<thead>' . '<tr>' . '<th>' . htmlspecialchars(tr('time')) . '_' . '</th>' . '<th>' . htmlspecialchars(tr('entry')) . '_' . '</th>' . '<th>' . htmlspecialchars(tr('level')) . '_' . '</th>' . '<th>' . htmlspecialchars(tr('pts')) . '_' . '</th>' . '<th>' . htmlspecialchars(tr('team')) . '_' . '</th>' . '<th>' . htmlspecialchars(tr('flag')) . '_' . '</th>' . '</tr>' . '</thead>' . $logs_tbody . '</table>';
      }
    } else {
      $logs_table =
        '<div class="fb-column-container">' . '<div class="col col-pad">' . htmlspecialchars(tr('No Entries')) . '</div>' . '</div>';
    }

    return
      '<div>' . '<header class="admin-page-header">' . '<h3>' . htmlspecialchars(tr('Game Logs')) . '</h3>' . '<span class="admin-section--status">' . htmlspecialchars(tr('status_')) . '<span class="highlighted">' . htmlspecialchars(tr('OK')) . '</span>' . '</span>' . '</header>' . '<div class="admin-sections">' . '<section class="admin-box">' . '<header class="admin-box-header">' . '<h3>' . htmlspecialchars(tr('Game Logs Timeline')) . '</h3>' . '</header>' . '<div class="fb-column-container">' . $logs_table . '</div>' . '</section>' . '</div>' . '</div>';
  }

  public function renderMainContent(): string {
    return '<h1>' . htmlspecialchars(tr('ADMIN')) . '</h1>';
  }

  public function renderMainNav(): string {
    $game = Configuration::get('game');
    $game_status = $game->getValue() === '1';
    $pause_action = '';
    if ($game_status) {
      $game_action =
        '<a href="#" class="fb-cta cta--red js-end-game">' . htmlspecialchars(tr('End Game')) . '</a>';
      $pause = Configuration::get('game_paused');
      $game_paused = $pause->getValue() === '1';
      if ($game_paused) {
        $pause_action =
          '<a href="#" class="fb-cta cta--yellow js-unpause-game">' . htmlspecialchars(tr('Unpause Game')) . '</a>';
      } else {
        $pause_action =
          '<a href="#" class="fb-cta cta--red js-pause-game">' . htmlspecialchars(tr('Pause Game')) . '</a>';
      }
    } else {
      $game_action =
        '<a href="#" class="fb-cta cta--yellow js-begin-game">' . htmlspecialchars(tr('Begin Game')) . '</a>';
    }
    $branding_xhp = $this->renderBranding();
    return
      '<div id="fb-admin-nav" class="admin-nav-bar fb-row-container">' . '<header class="admin-nav-header row-fixed">' . '<h2>' . htmlspecialchars(tr('Game Admin')) . '</h2>' . '</header>' . '<nav class="admin-nav-links row-fluid">' . '<ul>' . '<li>' . '<a href="/index.php?p=admin&page=configuration">' . htmlspecialchars(tr('Configuration')) . '</a>' . '</li>' . '<li>' . '<a href="/index.php?p=admin&page=controls">' . htmlspecialchars(tr('Controls')) . '</a>' . '</li>' . '<li>' . '<a href="/index.php?p=admin&page=announcements">' . htmlspecialchars(tr('Announcements')) . '</a>' . '</li>' . '<li>' . '<a href="/index.php?p=admin&page=quiz">' . htmlspecialchars(tr('Levels')) . ': ' . htmlspecialchars(tr('Quiz')) . '</a>' . '</li>' . '<li>' . '<a href="/index.php?p=admin&page=flags">' . htmlspecialchars(tr('Levels')) . ': ' . htmlspecialchars(tr('Flags')) . '</a>' . '</li>' . '<li>' . '<a href="/index.php?p=admin&page=bases">' . htmlspecialchars(tr('Levels')) . ': ' . htmlspecialchars(tr('Bases')) . '</a>' . '</li>' . '<li>' . '<a href="/index.php?p=admin&page=categories">' . htmlspecialchars(tr('Levels')) . ': ' . htmlspecialchars(tr('Categories')) . '</a>' . '</li>' . '<li>' . '<a href="/index.php?p=admin&page=countries">' . htmlspecialchars(tr('Levels')) . ': ' . htmlspecialchars(tr('Countries')) . '</a>' . '</li>' . '<li>' . '<a href="/index.php?p=admin&page=teams">' . htmlspecialchars(tr('Teams')) . '</a>' . '</li>' . '<li>' . '<a href="/index.php?p=admin&page=logos">' . htmlspecialchars(tr('Teams')) . ': ' . htmlspecialchars(tr('Logos')) . '</a>' . '</li>' . '<li>' . '<a href="/index.php?p=admin&page=sessions">' . htmlspecialchars(tr('Teams')) . ': ' . htmlspecialchars(tr('Sessions')) . '</a>' . '</li>' . '<li>' . '<a href="/index.php?p=admin&page=logs">' . htmlspecialchars(tr('Game Logs')) . '</a>' . '</li>' . '</ul>' . '</nav>' . '<div class="admin-nav-controls row-fixed">' . $game_action . $pause_action . '</div>' . '<div class="admin-nav--footer row-fixed">' . '<a href="/index.php?p=game">' . htmlspecialchars(tr('Gameboard')) . '</a>' . '<a href="" class="js-prompt-logout">' . htmlspecialchars(tr('Logout')) . '</a>' . '<a>' . '</a>' . $branding_xhp . '</div>' . '</div>';
  }

  public function renderPage(string $page): string {
    switch ($page) {
      case 'main':
        // Render the configuration page by default
        return $this->renderConfigurationContent();
        break;
      case 'configuration':
        return $this->renderConfigurationContent();
        break;
      case 'controls':
        return $this->renderControlsContent();
        break;
      case 'announcements':
        return $this->renderAnnouncementsContent();
        break;
      case 'quiz':
        return $this->renderQuizContent();
        break;
      case 'flags':
        return $this->renderFlagsContent();
        break;
      case 'bases':
        return $this->renderBasesContent();
        break;
      case 'categories':
        return $this->renderCategoriesContent();
        break;
      case 'countries':
        return $this->renderCountriesContent();
        break;
      case 'teams':
        return $this->renderTeamsContent();
        break;
      case 'logos':
        return $this->renderLogosContent();
        break;
      case 'sessions':
        return $this->renderSessionsContent();
        break;
      case 'logs':
        return $this->renderLogsContent();
        break;
      default:
        return $this->renderMainContent();
        break;
    }
  }
  public function renderBody(string $page): string {
    list($rendered_page, $rendered_main_nav) = [
      $this->renderPage($page),
      $this->renderMainNav(),
    ];
    return
      '<body data-section="admin">' . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(SessionUtils::CSRFToken()) . '" />' . '<div style="height: 0; width: 0; position: absolute; visibility: hidden" id="fb-svg-sprite">' . '</div>' . '<div class="fb-viewport admin-viewport">' . $rendered_main_nav . '<div id="fb-main-content" class="fb-page fb-admin-main">' . $rendered_page . '</div>' . '</div>' . '<script type="text/javascript" src="static/dist/js/app.js">' . '</script>' . '</body>';
  }
}
