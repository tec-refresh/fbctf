<?php declare(strict_types=1);

class IndexAjaxController extends AjaxController {
  protected function getFilters(): array {
    return [
      'POST' => [
        'team_id' => FILTER_VALIDATE_INT,
        'team_name' => FILTER_UNSAFE_RAW,
        'password' => FILTER_UNSAFE_RAW,
        'logo' => [
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => ['regexp' => '/^[\w+-\/]+={0,2}$/'],
        ],
        'isCustomLogo' => FILTER_VALIDATE_BOOLEAN,
        'logoType' => FILTER_UNSAFE_RAW,
        'token' => [
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => ['regexp' => '/^[\w]+$/'],
        ],
        'names' => FILTER_UNSAFE_RAW,
        'emails' => FILTER_UNSAFE_RAW,
        'action' => [
          'filter' => FILTER_VALIDATE_REGEXP,
          'options' => ['regexp' => '/^[\w\-]+$/'],
        ],
      ],
    ];
  }
  protected function getActions(): array {
    return ['register_team', 'register_names', 'login_team'];
  }
  protected function handleAction(
    string $action,
    array $params,
  ): string {
    switch ($action) {
      case 'none':
        return Utils::error_response('Invalid action', 'index');
      case 'register_team':
        return $this->registerTeam(
          must_have_string($params, 'team_name'),
          must_have_string($params, 'password'),
          strval(must_have_idx($params, 'token')),
          must_have_string($params, 'logo'),
          must_have_bool($params, 'isCustomLogo'),
          strval(must_have_idx($params, 'logoType')),
          false,
          [],
          [],
        );
      case 'register_names':
        $names = json_decode(must_have_string($params, 'names'));
        $emails = json_decode(must_have_string($params, 'emails'));
        if (!(is_array($names) && is_array($emails))) { throw new \RuntimeException('names and emails should be arrays'); }

        return $this->registerTeam(
          must_have_string($params, 'team_name'),
          must_have_string($params, 'password'),
          strval(must_have_idx($params, 'token')),
          must_have_string($params, 'logo'),
          must_have_bool($params, 'isCustomLogo'),
          strval(must_have_idx($params, 'logoType')),
          true,
          $names,
          $emails,
        );
      case 'login_team':
        $team_id = null;
        $login_select = Configuration::get('login_select');
        if ($login_select->getValue() === '1') {
          $team_id = must_have_int($params, 'team_id');
        } else {
          $team_name = must_have_string($params, 'team_name');
          $team_exists = Team::teamExist($team_name);
          if ($team_exists) {
            $team = Team::teamByName($team_name);
            $team_id = $team->getId();
          } else {
            return Utils::error_response('Login failed', 'login');
          }
        }
        if (!(is_int($team_id))) { throw new \RuntimeException('team_id should be an int'); }

        $password = must_have_string($params, 'password');

        // If we are here, login!
        return $this->loginTeam($team_id, $password);
      default:
        return Utils::error_response('Invalid action', 'index');
    }
  }

  private function registerTeam(
    string $team_name,
    string $password,
    ?string $token,
    string $logo,
    bool $is_custom_logo,
    ?string $logo_type,
    bool $register_names,
    array $names,
    array $emails,
  ): string {
    $ldap_password = $password;

    $awaitables = [
      'registration' => Configuration::get('registration'),
      'login_strongpasswords' => Configuration::get('login_strongpasswords'),
      'ldap' => Configuration::get('ldap'),
      'registration_type' => Configuration::get('registration_type'),
    ];
    $awaitables_results = $awaitables;

    $registration = $awaitables_results['registration'];
    $login_strongpasswords = $awaitables_results['login_strongpasswords'];
    $ldap = $awaitables_results['ldap'];
    $registration_type = $awaitables_results['registration_type'];

    // Check if registration is enabled
    if ($registration->getValue() === '0') {
      return Utils::error_response('Registration failed', 'registration');
    }

    // Check if strongs passwords are enforced
    if ($login_strongpasswords->getValue() !== '0') {
      $password_type = Configuration::getCurrentPasswordType();
      if (!preg_match(strval($password_type->getValue()), $password)) {
        return Utils::error_response('Password too simple', 'registration');
      }
    }

    // Check if ldap is enabled and verify credentials if successful
    $ldap_password = '';
    if ($ldap->getValue() === '1') {
      // Get server information from configuration
      list($ldap_server, $ldap_port, $ldap_domain_suffix) =
        [
          Configuration::get('ldap_server'),
          Configuration::get('ldap_port'),
          Configuration::get('ldap_domain_suffix'),
        ];
      // connect to ldap server
      $ldapconn = ldap_connect(
        $ldap_server->getValue(),
        intval($ldap_port->getValue()),
      );
      if (!$ldapconn)
        return Utils::error_response(
          'Could not connect to LDAP server',
          'registration',
        );
      $team_name = trim($team_name);
      $bind = ldap_bind(
        $ldapconn,
        $team_name.$ldap_domain_suffix->getValue(),
        $password,
      );
      if (!$bind)
        return
          Utils::error_response('LDAP Credentials Error', 'registration');
      // Use randomly generated password for local account for LDAP users
      // This will help avoid leaking users ldap passwords if the server's database
      // is compromised.
      $ldap_password = $password;
      $password = strval(bin2hex(random_bytes(100)));
    }

    // Check if tokenized registration is enabled
    if ($registration_type->getValue() === '2') {
      $token_check = Token::check((string) $token);
      // Check provided token
      if ($token === null || !$token_check) {
        return Utils::error_response('Registration failed', 'registration');
      }
    }

    // Check logo
    $logo_name = $logo;

    if ($is_custom_logo) {
      $custom_logo = Logo::createCustom($logo);
      if ($custom_logo) {
        $logo_name = $custom_logo->getName();
      } else {
        return Utils::error_response('Registration failed', 'registration');
      }
    }

    $logo_exists = Logo::checkExists($logo_name);
    if (!$logo_exists) {
      $logo_name = Logo::randomLogo();
    }

    // Check if team name is not empty or just spaces
    if (trim($team_name) === '') {
      return Utils::error_response('Registration failed', 'registration');
    }

    // Trim team name to 20 chars, to avoid breaking UI
    $shortname = substr($team_name, 0, 20);

    // Verify that this team name is not created yet
    $team_exists = Team::teamExist($shortname);
    if (!$team_exists) {
      if (!(is_string($password))) { throw new \RuntimeException("Expected password to be a string"); }
      $password_hash = Team::generateHash($password);
      $team_id =
        Team::create($shortname, $password_hash, $logo_name);
      if ($team_id) {
        // Store team players data, if enabled
        if ($register_names) {
          for ($i = 0; $i < count($names); $i++) {
            Team::addTeamData($names[$i], $emails[$i], $team_id);
          }
        }
        // If registration is tokenized, use the token
        if ($registration_type->getValue() === '2') {
          if (!($token !== null)) { throw new \RuntimeException('token should not be null'); }
          Token::use($token, $team_id);
        }
        // Login the team
        if ($ldap->getValue() === '1') {
          return $this->loginTeam($team_id, $ldap_password);
        } else {
          return $this->loginTeam($team_id, $password);
        }
      } else {
        return Utils::error_response('Registration failed', 'registration');
      }
    } else {
      return Utils::error_response('Registration failed', 'registration');
    }
  }

  private function loginTeam(
    int $team_id,
    string $password,
  ): string {
    // Verify credentials first so we can allow admins to login regardless of the login setting
    list($team, $login) = [
      Team::verifyCredentials($team_id, $password),
      Configuration::get('login'),
    ];
    // Check if login is disabled and this isn't an admin
    if (($login->getValue() === '0') &&
        ($team === null || $team->getAdmin() === false)) {
      return Utils::error_response('Login failed', 'login');
    }

    // Otherwise let's login any valid attempt
    if ($team) {
      SessionUtils::sessionRefresh();
      if (!SessionUtils::sessionActive()) {
        SessionUtils::sessionSet('team_id', strval($team->getId()));
        SessionUtils::sessionSet('name', $team->getName());
        SessionUtils::sessionSet(
          'csrf_token',
          (string) strval(bin2hex(random_bytes(100))),
        );
        SessionUtils::sessionSet(
          'IP',
          must_have_string(Utils::getSERVER(), 'REMOTE_ADDR'),
        );
        if ($team->getAdmin()) {
          SessionUtils::sessionSet('admin', strval($team->getAdmin()));
        }
      }
      if ($team->getAdmin()) {
        $redirect = 'admin';
      } else {
        $redirect = 'game';
      }
      return Utils::ok_response('Login succesful', $redirect);
    } else {
      return Utils::error_response('Login failed', 'login');
    }
  }
}
