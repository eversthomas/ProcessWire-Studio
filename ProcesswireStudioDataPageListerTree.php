<?php namespace ProcessWire;

/**
 * ProcesswireStudioDataPageListerTree
 * 
 * Verwaltet PageTree-Anpassungen für DataPageLister
 */
class ProcesswireStudioDataPageListerTree extends Wire {

  /**
   * Kinder im Seitenbaum ausblenden
   */
  public static function hookPageListable(ProcesswireStudioDataPageLister $lister, HookEvent $event) : void {
    $page = $event->object;
    $parent = $page->parent;
    if(!$parent || !$parent->id) return;

    $process = wire('process');
    if(!$process instanceof \ProcessWire\ProcessPageList) return;

    if($lister->isDataContainer($parent)) {
      $event->return = false;
      $event->replace = true;
    }
  }

  /**
   * PageTree-Aktion "Bearbeiten" -> "Tabelle" umbenennen
   */
  public static function hookPageListActionsRename(ProcesswireStudioDataPageLister $lister, HookEvent $event) : void {
    $page = $event->arguments(0);
    $actions = $event->return;
    if(!$page || !$page->id) return;

    if(!$lister->isDataContainer($page)) return;

    foreach($actions as &$a) {
      if(($a['name'] ?? '') === 'edit') {
        $a['label'] = wire()->_('Tabelle');
        $a['icon'] = 'table';
      }
    }
    $event->return = $actions;
  }
}
