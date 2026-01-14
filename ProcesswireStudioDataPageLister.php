<?php namespace ProcessWire;

/**
 * ProcesswireStudioDataPageLister
 * 
 * Integrierte DataPageLister-Funktionalität für ProcesswireStudio
 * Zeigt tabellarische Übersicht von Kindseiten für konfigurierte Container-Templates
 */
class ProcesswireStudioDataPageLister extends Wire {

  /**
   * Prüft ob eine Seite ein konfigurierter Daten-Container ist
   */
  public function isDataContainer(Page $page) : bool {
    if(!$page->template) return false;
    
    $studio = $this->wire('modules')->get('ProcesswireStudio');
    if(!$studio) return false;
    
    $config = $studio->wire('modules')->getConfig($studio);
    $enabledTemplates = $config['dataPageListerTemplates'] ?? [];
    
    return in_array($page->template->name, $enabledTemplates, true);
  }

  /**
   * Holt die Konfiguration für ein Template
   */
  public function getTemplateConfig(string $templateName) : ?array {
    $studio = $this->wire('modules')->get('ProcesswireStudio');
    if(!$studio) return null;
    
    $config = $studio->wire('modules')->getConfig($studio);
    $templateConfigs = $config['dataPageListerConfigs'] ?? [];
    
    return $templateConfigs[$templateName] ?? null;
  }

  /**
   * Ermittelt Kind-Templates einer Elternseite
   */
  public function getChildTemplates(Page $parent) : array {
    $tpls = [];
    
    // Zuerst: vorhandene Kinder prüfen
    foreach($parent->children('limit=10') as $c) {
      $tpls[$c->template->name] = $c->template;
    }
    
    // Fallback: erlaubte Kind-Templates des Parent-Templates
    if(!count($tpls) && $parent->template && count($parent->template->childTemplates)) {
      foreach($parent->template->childTemplates as $t) {
        $tpls[$t->name] = $t;
      }
    }
    
    return array_values($tpls);
  }

  /**
   * Bestimmt sichtbare Felder für die Tabelle
   */
  public function selectDisplayFields(Page $parent, array $childTemplates) : array {
    if(!count($childTemplates)) return [];
    
    $templateName = $parent->template->name;
    $config = $this->getTemplateConfig($templateName);
    
    // Wenn manuelle Feldliste konfiguriert
    if($config && isset($config['mode']) && $config['mode'] === 'manual') {
      $manualFields = $this->parseFieldList($config['fields'] ?? '');
      
      if(count($manualFields)) {
        $availableFields = $this->listAllowedFieldNames($childTemplates[0]);
        $validFields = array_intersect($manualFields, $availableFields);
        
        if(count($validFields)) {
          return array_values($validFields);
        }
      }
    }
    
    // Fallback: Automatische Erkennung
    $numFields = isset($config['numFields']) ? (int)$config['numFields'] : 5;
    
    if(isset($config['fieldSelectionMode']) && $config['fieldSelectionMode'] === 'common' && count($childTemplates) > 1) {
      return $this->selectCommonFields($childTemplates, $numFields);
    }
    
    return array_slice($this->listAllowedFieldNames($childTemplates[0]), 0, $numFields);
  }

  /**
   * Schnittmenge aller Templates (für common-Modus)
   */
  protected function selectCommonFields(array $childTemplates, int $n) : array {
    $firstList = $this->listAllowedFieldNames($childTemplates[0]);
    $common = $firstList;
    
    foreach(array_slice($childTemplates, 1) as $tpl) {
      $set = $this->listAllowedFieldNames($tpl);
      $common = array_values(array_intersect($common, $set));
    }
    
    return array_slice($common, 0, $n);
  }

  /**
   * Parst komma-separierte Feldliste
   */
  protected function parseFieldList(string $fields) : array {
    if(empty($fields)) return [];
    $parts = explode(',', $fields);
    return array_values(array_filter(array_map('trim', $parts)));
  }

  /**
   * Erlaubte Feldnamen (Systemfelder raus, Typ-Whitelist)
   */
  protected function listAllowedFieldNames(Template $tpl) : array {
    $out = [];
    foreach($tpl->fieldgroup as $f) {
      if($this->isSystemOrTitle($f)) continue;
      if(!$this->isAllowedField($f)) continue;
      $out[] = $f->name;
    }
    return $out;
  }

  protected function isSystemOrTitle(Field $f) : bool {
    return in_array($f->name, ['title','name','sort','created','modified','status'], true);
  }

  protected function isAllowedField(Field $f) : bool {
    $allowedTypes = [
      'FieldtypeText','FieldtypeTextarea','FieldtypePageTitle',
      'FieldtypeInteger','FieldtypeFloat','FieldtypeCheckbox',
      'FieldtypeDatetime','FieldtypeEmail','FieldtypeURL',
      'FieldtypeOptions','FieldtypePage'
    ];
    return in_array($f->type->className(), $allowedTypes, true);
  }
}
