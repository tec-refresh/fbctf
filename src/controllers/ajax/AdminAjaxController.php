<?php declare(strict_types=1);

class AdminAjaxController extends AjaxController {
  protected function getFilters(): array {
    return [
      'POST' => [
        'level_id' => FILTER_VALIDATE_INT,
        'level_type' => [
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => ['regexp' => '/^[a-z]{4}$/'],
        ],
        'team_id' => FILTER_VALIDATE_INT,
        'session_id' => FILTER_VALIDATE_INT,
        'cookie' => FILTER_UNSAFE_RAW,
        'data' => FILTER_UNSAFE_RAW,
        'last_page_access' => FILTER_UNSAFE_RAW,
        'name' => FILTER_UNSAFE_RAW,
        'password' => FILTER_UNSAFE_RAW,
        'admin' => FILTER_VALIDATE_INT,
        'status' => FILTER_VALIDATE_INT,
        'visible' => FILTER_VALIDATE_INT,
        'all_type' => [
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => ['regexp' => '/^[a-z]{4}$/'],
        ],
        'logo_id' => FILTER_VALIDATE_INT,
        'logo' => [
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => ['regexp' => '/^[\w-.]+$/'],
        ],
        'logo_b64' => [
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => ['regexp' => '/^[\w+-\/]+={0,2}$/'],
        ],
        'entity_id' => FILTER_VALIDATE_INT,
        'attachment_id' => FILTER_VALIDATE_INT,
        'filename' => [
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => ['regexp' => '/^[\w\-\.]+$/'],
        ],
        'attachment_file' => FILTER_UNSAFE_RAW,
        'game_file' => FILTER_UNSAFE_RAW,
        'teams_file' => FILTER_UNSAFE_RAW,
        'levels_file' => FILTER_UNSAFE_RAW,
        'categories_file' => FILTER_UNSAFE_RAW,
        'logos_file' => FILTER_UNSAFE_RAW,
        'link_id' => FILTER_VALIDATE_INT,
        'link' => FILTER_UNSAFE_RAW,
        'category_id' => FILTER_VALIDATE_INT,
        'category' => FILTER_UNSAFE_RAW,
        'country_id' => FILTER_VALIDATE_INT,
        'title' => FILTER_UNSAFE_RAW,
        'description' => FILTER_UNSAFE_RAW,
        'question' => FILTER_UNSAFE_RAW,
        'flag' => FILTER_UNSAFE_RAW,
        'answer' => FILTER_UNSAFE_RAW,
        'hint' => FILTER_UNSAFE_RAW,
        'points' => FILTER_VALIDATE_INT,
        'bonus' => FILTER_VALIDATE_INT,
        'bonus_dec' => FILTER_VALIDATE_INT,
        'penalty' => FILTER_VALIDATE_INT,
        'active' => FILTER_VALIDATE_INT,
        'field' => FILTER_UNSAFE_RAW,
        'value' => FILTER_UNSAFE_RAW,
        'announcement' => FILTER_UNSAFE_RAW,
        'announcement_id' => FILTER_VALIDATE_INT,
        'csrf_token' => FILTER_UNSAFE_RAW,
        'action' => [
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => ['regexp' => '/^[\w-]+$/'],
        ],
        'page' => [
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => ['regexp' => '/^[\w-]+$/'],
        ],
      ],
      'GET' => [
        'action' => [
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => ['regexp' => '/^[\w-]+$/'],
        ],
        'csrf_token' => FILTER_UNSAFE_RAW,
      ],
    ];
  }
  protected function getActions(): array {
    return [
      'create_team',
      'create_quiz',
      'update_quiz',
      'create_flag',
      'update_flag',
      'create_base',
      'update_base',
      'update_team',
      'delete_team',
      'delete_level',
      'delete_all',
      'update_session',
      'delete_session',
      'toggle_status_level',
      'toggle_status_all',
      'toggle_status_team',
      'toggle_admin_team',
      'toggle_visible_team',
      'enable_country',
      'disable_country',
      'create_category',
      'update_category',
      'delete_category',
      'enable_logo',
      'disable_logo',
      'create_attachment',
      'update_attachment',
      'delete_attachment',
      'create_link',
      'update_link',
      'delete_link',
      'begin_game',
      'change_configuration',
      'change_custom_logo',
      'create_announcement',
      'delete_announcement',
      'create_tokens',
      'end_game',
      'pause_game',
      'unpause_game',
      'reset_game',
      'export_attachments',
      'backup_db',
      'export_game',
      'export_teams',
      'export_logos',
      'export_levels',
      'export_categories',
      'restore_db',
      'import_game',
      'import_teams',
      'import_logos',
      'import_levels',
      'import_categories',
      'import_attachments',
      'reset_game_schedule',
      'flush_memcached',
      'reset_database',
    ];
  }
  protected function handleAction(
    string $action,
    array $params,
  ): string {
    if ($action !== 'none') {
      // CSRF check
      if (idx($params, 'csrf_token') !== SessionUtils::CSRFToken()) {
        return Utils::error_response('CSRF token is invalid', 'admin');
      }
    }

    list($default_bonus, $default_bonusdec) = [
      Configuration::get('default_bonus'),
      Configuration::get('default_bonusdec'),
    ];

    switch ($action) {
      case 'none':
        return Utils::error_response('Invalid action', 'admin');
      case 'create_quiz':
        $bonus = $default_bonus->getValue();
        $bonus_dec = $default_bonusdec->getValue();
        Level::createQuiz(
          must_have_string($params, 'title'),
          must_have_string($params, 'question'),
          must_have_string($params, 'answer'),
          must_have_int($params, 'entity_id'),
          must_have_int($params, 'points'),
          intval($bonus),
          intval($bonus_dec),
          must_have_string($params, 'hint'),
          intval(must_have_idx($params, 'penalty')),
        );
        return Utils::ok_response('Created succesfully', 'admin');
      case 'update_quiz':
        Level::updateQuiz(
          must_have_string($params, 'title'),
          must_have_string($params, 'question'),
          must_have_string($params, 'answer'),
          must_have_int($params, 'entity_id'),
          must_have_int($params, 'points'),
          must_have_int($params, 'bonus'),
          must_have_int($params, 'bonus_dec'),
          must_have_string($params, 'hint'),
          intval(must_have_idx($params, 'penalty')),
          must_have_int($params, 'level_id'),
        );
        return Utils::ok_response('Updated succesfully', 'admin');
      case 'create_flag':
        $bonus = $default_bonus->getValue();
        $bonus_dec = $default_bonusdec->getValue();
        Level::createFlag(
          must_have_string($params, 'title'),
          must_have_string($params, 'description'),
          must_have_string($params, 'flag'),
          must_have_int($params, 'entity_id'),
          must_have_int($params, 'category_id'),
          must_have_int($params, 'points'),
          intval($bonus),
          intval($bonus_dec),
          must_have_string($params, 'hint'),
          intval(must_have_idx($params, 'penalty')),
        );
        return Utils::ok_response('Created succesfully', 'admin');
      case 'update_flag':
        Level::updateFlag(
          must_have_string($params, 'title'),
          must_have_string($params, 'description'),
          must_have_string($params, 'flag'),
          must_have_int($params, 'entity_id'),
          must_have_int($params, 'category_id'),
          must_have_int($params, 'points'),
          must_have_int($params, 'bonus'),
          must_have_int($params, 'bonus_dec'),
          must_have_string($params, 'hint'),
          intval(must_have_idx($params, 'penalty')),
          must_have_int($params, 'level_id'),
        );
        return Utils::ok_response('Updated succesfully', 'admin');
      case 'create_base':
        $bonus = $default_bonus->getValue();
        Level::createBase(
          must_have_string($params, 'title'),
          must_have_string($params, 'description'),
          must_have_int($params, 'entity_id'),
          must_have_int($params, 'category_id'),
          must_have_int($params, 'points'),
          must_have_int($params, 'bonus'),
          must_have_string($params, 'hint'),
          intval(must_have_idx($params, 'penalty')),
        );
        return Utils::ok_response('Created succesfully', 'admin');
      case 'update_base':
        Level::updateBase(
          must_have_string($params, 'title'),
          must_have_string($params, 'description'),
          must_have_int($params, 'entity_id'),
          must_have_int($params, 'category_id'),
          must_have_int($params, 'points'),
          must_have_int($params, 'bonus'),
          must_have_string($params, 'hint'),
          intval(must_have_idx($params, 'penalty')),
          must_have_int($params, 'level_id'),
        );
        return Utils::ok_response('Updated succesfully', 'admin');
      case 'delete_level':
        Level::delete(must_have_int($params, 'level_id'));
        return Utils::ok_response('Deleted succesfully', 'admin');
      case 'toggle_status_level':
        Level::setStatus(
          must_have_int($params, 'level_id'),
          must_have_int($params, 'status') === 1,
        );
        return Utils::ok_response('Success', 'admin');
      case 'toggle_status_all':
        if (must_have_string($params, 'all_type') === 'team') {
          Team::setStatusAll(must_have_int($params, 'status') === 1);
          return Utils::ok_response('Success', 'admin');
        } else {
          Level::setStatusAll(
            must_have_int($params, 'status') === 1,
            must_have_string($params, 'all_type'),
          );
          return Utils::ok_response('Success', 'admin');
        }
      case 'create_team':
        $password_hash =
          Team::generateHash(must_have_string($params, 'password'));
        Team::create(
          must_have_string($params, 'name'),
          $password_hash,
          must_have_string($params, 'logo'),
        );
        return Utils::ok_response('Created succesfully', 'admin');
      case 'update_team':
        Team::update(
          must_have_string($params, 'name'),
          must_have_string($params, 'logo'),
          must_have_int($params, 'points'),
          must_have_int($params, 'team_id'),
        );
        if (strlen(must_have_string($params, 'password')) > 0) {
          $password_hash =
            Team::generateHash(must_have_string($params, 'password'));
          Team::updateTeamPassword(
            $password_hash,
            must_have_int($params, 'team_id'),
          );
        }
        return Utils::ok_response('Updated succesfully', 'admin');
      case 'toggle_admin_team':
        Team::setAdmin(
          must_have_int($params, 'team_id'),
          must_have_int($params, 'admin') === 1,
        );
        return Utils::ok_response('Success', 'admin');
      case 'toggle_status_team':
        Team::setStatus(
          must_have_int($params, 'team_id'),
          must_have_int($params, 'status') === 1,
        );
        return Utils::ok_response('Success', 'admin');
      case 'toggle_visible_team':
        Team::setVisible(
          must_have_int($params, 'team_id'),
          must_have_int($params, 'visible') === 1,
        );
        return Utils::ok_response('Success', 'admin');
      case 'enable_logo':
        Logo::setEnabled(must_have_int($params, 'logo_id'), true);
        return Utils::ok_response('Success', 'admin');
      case 'disable_logo':
        Logo::setEnabled(must_have_int($params, 'logo_id'), false);
        return Utils::ok_response('Success', 'admin');
      case 'enable_country':
        Country::setStatus(
          must_have_int($params, 'country_id'),
          true,
        );
        return Utils::ok_response('Success', 'admin');
      case 'disable_country':
        Country::setStatus(
          must_have_int($params, 'country_id'),
          false,
        );
        return Utils::ok_response('Success', 'admin');
      case 'delete_team':
        // Delete team and associated sessions
        [
          Session::deleteByTeam(must_have_int($params, 'team_id')),
          Team::delete(must_have_int($params, 'team_id')),
        ];
        return Utils::ok_response('Deleted successfully', 'admin');
      case 'update_session':
        Session::update(
          must_have_string($params, 'cookie'),
          must_have_string($params, 'data'),
        );
        return Utils::ok_response('Updated successfully', 'admin');
      case 'delete_session':
        Session::delete(must_have_string($params, 'cookie'));
        return Utils::ok_response('Deleted successfully', 'admin');
      case 'delete_category':
        Category::delete(must_have_int($params, 'category_id'));
        return Utils::ok_response('Deleted successfully', 'admin');
      case 'create_category':
        Category::create(
          must_have_string($params, 'category'),
          false,
        );
        return Utils::ok_response('Created successfully', 'admin');
      case 'update_category':
        Category::update(
          must_have_string($params, 'category'),
          must_have_int($params, 'category_id'),
        );
        return Utils::ok_response('Updated successfully', 'admin');
      case 'create_attachment':
        $result = Attachment::create(
          'attachment_file',
          must_have_string($params, 'filename'),
          must_have_int($params, 'level_id'),
        );
        if ($result) {
          return Utils::ok_response('Created successfully', 'admin');
        } else {
          return ''; // TODO
        }
      case 'update_attachment':
        Attachment::update(
          must_have_int($params, 'attachment_id'),
          must_have_int($params, 'level_id'),
          must_have_string($params, 'filename'),
        );
        return Utils::ok_response('Updated successfully', 'admin');
      case 'delete_attachment':
        Attachment::delete(must_have_int($params, 'attachment_id'));
        return Utils::ok_response('Deleted successfully', 'admin');
      case 'create_link':
        Link::create(
          must_have_string($params, 'link'),
          must_have_int($params, 'level_id'),
        );
        return Utils::ok_response('Created successfully', 'admin');
      case 'update_link':
        Link::update(
          must_have_string($params, 'link'),
          must_have_int($params, 'level_id'),
          must_have_int($params, 'link_id'),
        );
        return Utils::ok_response('Updated succesfully', 'admin');
      case 'delete_link':
        Link::delete(must_have_int($params, 'link_id'));
        return Utils::ok_response('Deleted successfully', 'admin');
      case 'change_configuration':
        $field = must_have_string($params, 'field');
        $valid_field = Configuration::validField($field);
        if ($valid_field) {
          Configuration::update(
            $field,
            must_have_string($params, 'value'),
          );
          return Utils::ok_response('Success', 'admin');
        } else {
          return Utils::error_response('Invalid configuration', 'admin');
        }
      case 'change_custom_logo':
        $logo = must_have_string($params, 'logo_b64');
        $custom_logo = Logo::createCustom($logo, true);
        if ($custom_logo) {
          return Utils::ok_response('Success', 'admin');
        } else {
          return Utils::error_response('Error changing logo', 'admin');
        }
      case 'create_announcement':
        Announcement::create(
          must_have_string($params, 'announcement'),
        );
        return Utils::ok_response('Success', 'admin');
      case 'delete_announcement':
        Announcement::delete(
          must_have_int($params, 'announcement_id'),
        );
        return Utils::ok_response('Success', 'admin');
      case 'create_tokens':
        Token::create();
        return Utils::ok_response('Success', 'admin');
      case 'export_tokens':
        Token::export();
        return Utils::ok_response('Success', 'admin');
      case 'begin_game':
        Control::begin();
        return Utils::ok_response('Success', 'admin');
      case 'end_game':
        Control::end();
        return Utils::ok_response('Success', 'admin');
      case 'pause_game':
        Control::pause();
        return Utils::ok_response('Success', 'admin');
      case 'unpause_game':
        Control::unpause();
        return Utils::ok_response('Success', 'admin');
      case 'export_attachments':
        Control::exportAttachments();
        return Utils::ok_response('Success', 'admin');
      case 'backup_db':
        Control::backupDb();
        return Utils::ok_response('Success', 'admin');
      case 'export_game':
        Control::exportGame();
        return Utils::ok_response('Success', 'admin');
      case 'export_teams':
        Control::exportTeams();
        return Utils::ok_response('Success', 'admin');
      case 'export_logos':
        Control::exportLogos();
        return Utils::ok_response('Success', 'admin');
      case 'export_levels':
        Control::exportLevels();
        return Utils::ok_response('Success', 'admin');
      case 'export_categories':
        Control::exportCategories();
        return Utils::ok_response('Success', 'admin');
      case 'restore_db':
        $result = Control::restoreDb();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error importing', 'admin');
      case 'import_game':
        $result = Control::importGame();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error importing', 'admin');
      case 'import_teams':
        $result = Control::importTeams();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error importing', 'admin');
      case 'import_logos':
        $result = Control::importLogos();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error importing', 'admin');
      case 'import_levels':
        $result = Control::importLevels();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error importing', 'admin');
      case 'import_categories':
        $result = Control::importCategories();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error importing', 'admin');
      case 'import_attachments':
        $result = Control::importAttachments();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error importing', 'admin');
      case 'reset_game_schedule':
        [
          Configuration::update('start_ts', '0'), // Put timestamps to zero
          Configuration::update('end_ts', '0'),
          Configuration::update('next_game', '0'),
        ];
        return Utils::ok_response('Success', 'admin');
      case 'flush_memcached':
        $result = Control::flushMemcached();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error flushing memcached', 'admin');
      case 'reset_database':
        $result = Control::resetDatabase();
        if ($result) {
          return Utils::ok_response('Success', 'admin');
        }
        return Utils::error_response('Error resetting database', 'admin');
      default:
        return Utils::error_response('Invalid action', 'admin');
    }
  }
}
