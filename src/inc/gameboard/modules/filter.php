<?php declare(strict_types=1);

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class FilterModuleController extends ModuleController {
  public function render(): string {

    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    tr_start();
    $category_items = '';
    $category_items .=
      '<li>' .
        '<input' .
          ' type="radio"' .
          ' name="fb--module--filter--category"' .
          ' value="all"' .
          ' id="fb--module--filter--category--all"' .
          ' checked' .
        '>' .
        '<label for="fb--module--filter--category--all" class="click-effect">' .
          '<span>' . htmlspecialchars(tr('All')) . '</span>' .
        '</label>' .
      '</li>';

    $categories = Category::allCategories();

    foreach ($categories as $category) {
      $category_id =
        'fb--module--filter--category--' . strtolower($category->getCategory());
      $category_items .=
        '<li>' .
          '<input' .
            ' type="radio"' .
            ' name="fb--module--filter--category"' .
            ' value="' . htmlspecialchars($category->getCategory()) . '"' .
            ' id="' . htmlspecialchars($category_id) . '"' .
          '>' .
          '<label for="' . htmlspecialchars($category_id) . '" class="click-effect">' .
            '<span>' . htmlspecialchars($category->getCategory()) . '</span>' .
          '</label>' .
        '</li>';
    }

    return
      '<div>' .
        '<header class="module-header">' .
          '<h6>' . htmlspecialchars(tr('Filter')) . '</h6>' .
        '</header>' .
        '<div class="module-content">' .
          '<div class="fb-section-border">' .
            '<div class="radio-tabs">' .
              '<input' .
                ' type="radio"' .
                ' name="fb--module--filter"' .
                ' value="category"' .
                ' id="fb--module--filter--category"' .
                ' checked' .
              '>' .
              '<label for="fb--module--filter--category" class="click-effect">' .
                '<span>' . htmlspecialchars(tr('Category')) . '</span>' .
              '</label>' .
              '<input' .
                ' type="radio"' .
                ' name="fb--module--filter"' .
                ' value="status"' .
                ' id="fb--module--filter--status"' .
              '>' .
              '<label for="fb--module--filter--status" class="click-effect">' .
                '<span>' . htmlspecialchars(tr('Status')) . '</span>' .
              '</label>' .
            '</div>' .
            '<div class="tab-content-container module-scrollable">' .
              '<div' .
                ' class="radio-tab-content active"' .
                ' data-tab="category"' .
                ' id="category-filter-content">' .
                '<ul class="radio-list">' . $category_items . '</ul>' .
              '</div>' .
              '<div' .
                ' class="radio-tab-content"' .
                ' data-tab="status"' .
                ' id="status-filter-content">' .
                '<ul class="radio-list">' .
                  '<li>' .
                    '<input' .
                      ' type="radio"' .
                      ' name="fb--module--filter--status"' .
                      ' value="all"' .
                      ' id="fb--module--filter--status--all"' .
                      ' checked' .
                    '>' .
                    '<label' .
                      ' for="fb--module--filter--status--all"' .
                      ' class="click-effect">' .
                      '<span>' . htmlspecialchars(tr('All')) . '</span>' .
                    '</label>' .
                  '</li>' .
                  '<li>' .
                    '<input' .
                      ' type="radio"' .
                      ' name="fb--module--filter--status"' .
                      ' value="completed"' .
                      ' id="fb--module--filter--status--completed"' .
                    '>' .
                    '<label' .
                      ' for="fb--module--filter--status--completed"' .
                      ' class="click-effect">' .
                      '<span>' . htmlspecialchars(tr('Completed')) . '</span>' .
                    '</label>' .
                  '</li>' .
                  '<li>' .
                    '<input' .
                      ' type="radio"' .
                      ' name="fb--module--filter--status"' .
                      ' value="remaining"' .
                      ' id="fb--module--filter--status--remaining"' .
                    '>' .
                    '<label' .
                      ' for="fb--module--filter--status--remaining"' .
                      ' class="click-effect">' .
                      '<span>' . htmlspecialchars(tr('Remaining')) . '</span>' .
                    '</label>' .
                  '</li>' .
                '</ul>' .
              '</div>' .
            '</div>' .
          '</div>' .
        '</div>' .
      '</div>';
  }
}

$filter_generated = new FilterModuleController();
$filter_generated->sendRender();
