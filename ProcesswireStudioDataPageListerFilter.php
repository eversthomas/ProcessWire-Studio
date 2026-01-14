<?php namespace ProcessWire;

/**
 * ProcesswireStudioDataPageListerFilter
 * 
 * Baut Selector-Strings und Filterzustände
 */
class ProcesswireStudioDataPageListerFilter {

  /**
   * Baut den Selector anhand von GET-Parametern
   */
  public static function buildSelector(Page $parent, array $fieldNames, WireInput $input, Sanitizer $san) : array {
    $parts = ["parent=$parent"];
    $active = [];

    $allowed = array_merge(['title'], $fieldNames);

    $by = $san->name($input->get('by')) ?: 'title';
    if(!in_array($by, $allowed, true)) $by = 'title';

    $q = $san->text($input->get('q'));
    if($q) {
      $parts[] = "{$by}*={$q}";
      $active = ['by' => $by, 'q' => $q];
    }

    $sort = $san->name($input->get('sort')) ?: 'title';
    $dir = strtolower($san->text($input->get('dir'))) === 'desc' ? 'desc' : 'asc';
    $parts[] = "sort=$sort $dir";

    return [implode(', ', $parts), $active, $allowed];
  }

  /**
   * Markiert <option> als ausgewählt
   */
  public static function sel($val, $exp) {
    return $val === $exp ? 'selected' : '';
  }
}
