<?php declare(strict_types=1);

class Category extends Model implements Importable, Exportable {

  protected static string $MC_KEY = 'categories:';

  protected static array $MC_KEYS = [
    'ALL_CATEGORIES' => 'categories',
    'CATEGORIES' => 'categories_id',
  ];

  private function __construct(
    private int $id,
    private string $category,
    private int $protected,
    private string $created_ts,
  ) {}

  public function getId(): int {
    return $this->id;
  }

  public function getCategory(): string {
    return $this->category;
  }

  public function getProtected(): bool {
    return $this->protected === 1;
  }

  public function getCreatedTs(): string {
    return $this->created_ts;
  }

  private static function categoryFromRow(array $row): Category {
    return new Category(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'category'),
      intval(must_have_idx($row, 'protected')),
      must_have_idx($row, 'created_ts'),
    );
  }

  // Import levels.
  public static function importAll(
    array $elements,
  ): bool {
    foreach ($elements as $category) {
      $c = must_have_string($category, 'category');
      $exist = self::checkExists($c);
      if (!$exist) {
        self::create(
          $c,
          (bool) must_have_idx($category, 'protected'),
        );
      }
    }
    return true;
  }

  // Export levels.
  public static function exportAll(): array {
    $all_categories_data = [];
    $all_categories = self::allCategories();

    foreach ($all_categories as $category) {
      $one_category = [
        'category' => $category->getCategory(),
        'protected' => $category->getProtected(),
      ];
      array_push($all_categories_data, $one_category);
    }
    return ['categories' => $all_categories_data];
  }

  // All categories.
  public static function allCategories(
    bool $refresh = false,
  ): array {
    $mc_result = self::getMCRecords('ALL_CATEGORIES');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $categories = [];
      $result = $db->query('SELECT * FROM categories ORDER BY category ASC');
      foreach ($result->fetchAll() as $row) {
        $categories[] = self::categoryFromRow($row);
      }
      self::setMCRecords('ALL_CATEGORIES', $categories);
      return $categories;
    } else {
      if (!(is_array($mc_result))) {
        throw new RuntimeException('cache return should be an array of Category');
      }
      return $mc_result;
    }
  }

  // Check if category is used.
  public static function isUsed(int $category_id): bool {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT COUNT(*) FROM levels WHERE category_id = ?',
      [$category_id],
    );

    if ($result->rowCount() > 0) {
      if (!($result->rowCount() === 1)) {
        throw new RuntimeException('Expected exactly one result');
      }
      return intval($result->fetch()['COUNT(*)']) > 0;
    } else {
      return false;
    }
  }

  // Delete category.
  public static function delete(int $category_id): void {
    $db = Db::getInstance();

    $db->query(
      'DELETE FROM categories WHERE id = ? AND id NOT IN (SELECT category_id FROM levels) AND protected = 0 LIMIT 1',
      [$category_id],
    );
    self::invalidateMCRecords(); // Invalidate Memcached Category data.
  }

  // Create category.
  public static function create(
    string $category,
    bool $protected,
  ): int {
    $db = Db::getInstance();

    // Create category
    $db->query(
      'INSERT INTO categories (category, protected, created_ts) VALUES (?, ?, NOW())',
      [$category, (int) $protected],
    );

    // Return newly created category_id
    $result = $db->query(
      'SELECT id FROM categories WHERE category = ? LIMIT 1',
      [$category],
    );

    if (!($result->rowCount() === 1)) {
      throw new RuntimeException('Expected exactly one result');
    }
    self::invalidateMCRecords(); // Invalidate Memcached Category data.
    return intval($result->fetch()['id']);
  }

  // Update category.
  public static function update(
    string $category,
    int $category_id,
  ): void {
    $db = Db::getInstance();
    $db->query(
      'UPDATE categories SET category = ? WHERE id = ? LIMIT 1',
      [$category, $category_id],
    );
    self::invalidateMCRecords(); // Invalidate Memcached Category data.
  }

  // Get category by id.
  public static function singleCategory(
    int $category_id,
    bool $refresh = false,
  ): Category {
    $mc_result = self::getMCRecords('CATEGORIES');
    if (!$mc_result || count($mc_result) === 0 || $refresh) {
      $db = Db::getInstance();
      $categories = [];
      $result = $db->query('SELECT * FROM categories');
      foreach ($result->fetchAll() as $row) {
        $categories[intval($row['id'])] = self::categoryFromRow($row);
      }
      self::setMCRecords('CATEGORIES', $categories);
      if (!array_key_exists($category_id, $categories)) {
        throw new RuntimeException('category not found');
      }
      $category = $categories[$category_id];
      if (!($category instanceof Category)) {
        throw new RuntimeException('category should be type of Category');
      }
      return $category;
    } else {
      if (!(is_array($mc_result))) {
        throw new RuntimeException('categories should be type of array');
      }
      if (!array_key_exists($category_id, $mc_result)) {
        throw new RuntimeException('category not found');
      }
      $category = $mc_result[$category_id];
      if (!($category instanceof Category)) {
        throw new RuntimeException('category should be type of Category');
      }
      return $category;
    }
  }

  // Get category by name.
  public static function singleCategoryByName(
    string $category,
  ): Category {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT * FROM categories WHERE category = ? LIMIT 1',
      [$category],
    );

    if (!($result->rowCount() === 1)) {
      throw new RuntimeException('Expected exactly one result');
    }
    $category = self::categoryFromRow($result->fetch());

    return $category;
  }

  // Check if a category is already created.
  public static function checkExists(
    string $category,
  ): bool {
    $db = Db::getInstance();

    $result = $db->query(
      'SELECT COUNT(*) FROM categories WHERE category = ?',
      [$category],
    );

    if ($result->rowCount() > 0) {
      if (!($result->rowCount() === 1)) {
        throw new RuntimeException('Expected exactly one result');
      }
      return (intval(idx($result->fetch(), 'COUNT(*)')) > 0);
    } else {
      return false;
    }
  }
}
