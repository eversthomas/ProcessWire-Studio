<?php namespace ProcessWire;

/**
 * ProcesswireStudioDataPageListerRender
 * 
 * Rendert die tabellarische Übersicht mit UIkit-Styling
 */
class ProcesswireStudioDataPageListerRender {

  /**
   * Haupteinstieg: baut die gesamte Übersicht
   */
  public static function overview(
    Page $parent,
    array $fieldNames,
    PageArray $items,
    int $total,
    int $pageNum,
    int $limit,
    array $active,
    array $childTemplates,
    array $allowedFields,
    string $adminUrl,
    bool $showHelp,
    bool $showViewButton
  ) : string {

    $editBase = rtrim($adminUrl, '/') . "/page/edit/?id=" . (int) $parent->id;

    $out = self::renderJavaScript();
    $out .= self::renderHeader($parent, $childTemplates, $total);
    if($showHelp) {
      $out .= self::renderHelp($childTemplates, $fieldNames);
    }
    $out .= self::renderFilterBar($active, $allowedFields, $editBase);
    $out .= self::renderAddButton($parent, $childTemplates, $adminUrl);
    $out .= self::renderTable($fieldNames, $items, $showViewButton, $adminUrl);
    $out .= self::renderPager($pageNum, $limit, $total, $editBase, $active);

    return $out;
  }

  /**
   * JavaScript für Live-Filter
   */
  public static function renderJavaScript() : string {
    return <<<'HTML'
<script>
(function(){
  function debounce(fn, wait){ let t; return function(){ clearTimeout(t); const a=arguments, c=this; t=setTimeout(function(){ fn.apply(c,a); }, wait); }; }
  function toQuery(obj){
    const parts=[];
    for(const k in obj){
      if(Object.prototype.hasOwnProperty.call(obj,k)){
        const v=obj[k];
        if(v!==undefined && v!==null && v!==''){
          parts.push(encodeURIComponent(k)+'='+encodeURIComponent(v));
        }
      }
    }
    return parts.join('&');
  }
  function parseQuery(search){
    const out={};
    if(!search) return out;
    search.replace(/^\?/,'').split('&').forEach(function(kv){
      if(!kv) return;
      const i = kv.indexOf('=');
      if(i===-1){ out[decodeURIComponent(kv)]=''; return; }
      const k = decodeURIComponent(kv.slice(0,i));
      const v = decodeURIComponent(kv.slice(i+1));
      out[k]=v;
    });
    return out;
  }

  document.addEventListener('DOMContentLoaded', function(){
    var bar = document.querySelector('.pw-dpl-filters');
    if(!bar) return;

    var baseUrl = bar.getAttribute('data-base-url') || window.location.pathname;

    var inputQ  = bar.querySelector('input[name="q"]');
    var selBy   = bar.querySelector('select[name="by"]');
    var selSort = bar.querySelector('select[name="sort"]');
    var selDir  = bar.querySelector('select[name="dir"]');
    var btnApply= bar.querySelector('button[data-apply]');
    var btnReset= bar.querySelector('a[data-reset]');

    function navigate(resetPg){
      var q = parseQuery(window.location.search);
      if(inputQ)  q.q    = inputQ.value || '';
      if(selBy)   q.by   = selBy.value || '';
      if(selSort) q.sort = selSort.value || '';
      if(selDir)  q.dir  = selDir.value || '';

      Object.keys(q).forEach(function(k){ if(q[k]==='' || q[k]==null) delete q[k]; });

      if(resetPg) q.pg = 1;

      var qs = toQuery(q);
      var joinChar = (baseUrl.indexOf('?') !== -1) ? '&' : '?';
      window.location = baseUrl + (qs ? (joinChar + qs) : '');
    }

    if(btnApply) btnApply.addEventListener('click', function(){ navigate(true); });

    if(inputQ){
      inputQ.addEventListener('keydown', function(e){ if(e.key === 'Enter'){ e.preventDefault(); navigate(true); }});
      inputQ.addEventListener('input', debounce(function(){ navigate(true); }, 350));
    }

    [selBy, selSort, selDir].forEach(function(sel){
      if(sel) sel.addEventListener('change', function(){ navigate(true); });
    });

    if(btnReset){
      btnReset.addEventListener('click', function(e){
        e.preventDefault();
        window.location = baseUrl;
      });
    }
  });
})();
</script>
HTML;
  }

  /**
   * Header mit Meta-Info (UIkit)
   */
  public static function renderHeader(Page $parent, array $childTemplates, int $total) : string {
    $san = wire('sanitizer');
    $tplNames = implode(', ', array_map(function($tpl) use ($san) { 
      return $san->entities($tpl->name); 
    }, $childTemplates));
    $parentTitle = $san->entities((string) $parent->title);
    
    return "<div class='uk-card uk-card-default uk-card-body uk-margin-bottom'>
      <div class='uk-grid-small uk-flex-middle' uk-grid>
        <div class='uk-width-expand'>
          <h3 class='uk-card-title uk-margin-remove-bottom'>{$parentTitle}</h3>
          <p class='uk-text-meta uk-margin-remove-top'>
            {$total} " . (($total === 1) ? 'Eintrag' : 'Einträge') . " • Templates: {$tplNames}
          </p>
        </div>
      </div>
    </div>";
  }

  /**
   * Hilfe-Hinweis (UIkit)
   */
  public static function renderHelp(array $childTemplates, array $fieldNames) : string {
    $san = wire('sanitizer');
    $fieldsList = $fieldNames ? implode(', ', array_map([$san, 'entities'], $fieldNames)) : '—';
    $tpls = $childTemplates
      ? implode(', ', array_map(function($tpl) use ($san) { 
          return "<code>" . $san->entities($tpl->name) . "</code>"; 
        }, $childTemplates))
      : '—';
    
    return "<div class='uk-alert-primary uk-margin-bottom' uk-alert>
      <a class='uk-alert-close' uk-close></a>
      <h3 class='uk-margin-small-bottom'>💡 Hinweis</h3>
      <p class='uk-margin-remove-top'>
        Diese Übersicht zeigt den Titel sowie die definierten Felder der Kinder.<br>
        <strong>Templates:</strong> {$tpls}<br>
        <strong>Angezeigte Felder:</strong> {$fieldsList}
      </p>
    </div>";
  }

  /**
   * Filterbar (UIkit)
   */
  public static function renderFilterBar(array $active, array $allowedFields, string $baseUrl) : string {
    $san = wire('sanitizer');

    $q    = $san->entities($active['q']    ?? (wire('input')->get('q')    ?? ''));
    $by   = $san->entities($active['by']   ?? (wire('input')->get('by')   ?? 'title'));
    $sort = $san->entities($active['sort'] ?? (wire('input')->get('sort') ?? 'title'));
    $dir  = $san->entities($active['dir']  ?? (wire('input')->get('dir')  ?? 'asc'));

    $renderOpts = function($current) use ($allowedFields, $san) {
      $out = '';
      foreach ($allowedFields as $f) {
        $fname = $san->entities((string) $f);
        $sel = ($f == $current) ? 'selected' : '';
        $out .= "<option value=\"{$fname}\" {$sel}>{$fname}</option>";
      }
      return $out;
    };

    $byOptions   = $renderOpts($by);
    $sortOptions = $renderOpts($sort);
    $baseEsc  = $san->entities($baseUrl);

    return "<div class='uk-card uk-card-default uk-card-body uk-margin-bottom pw-dpl-filters' data-base-url='{$baseEsc}'>
      <div class='uk-grid-small' uk-grid>
        <div class='uk-width-1-1 uk-width-medium@m'>
          <input class='uk-input' type='text' name='q' value='{$q}' placeholder='Suche...'>
        </div>
        <div class='uk-width-1-2 uk-width-auto@m'>
          <select class='uk-select' name='by'>{$byOptions}</select>
        </div>
        <div class='uk-width-1-2 uk-width-auto@m'>
          <select class='uk-select' name='sort'>{$sortOptions}</select>
        </div>
        <div class='uk-width-1-2 uk-width-auto@m'>
          <select class='uk-select' name='dir'>
            <option value='asc'  ".($dir==='asc'?'selected':'').">aufsteigend</option>
            <option value='desc' ".($dir==='desc'?'selected':'').">absteigend</option>
          </select>
        </div>
        <div class='uk-width-1-2 uk-width-auto@m'>
          <button class='uk-button uk-button-primary' type='button' data-apply>Anwenden</button>
        </div>
        <div class='uk-width-1-2 uk-width-auto@m'>
          <a class='uk-button uk-button-default' href='#' data-reset>Zurücksetzen</a>
        </div>
      </div>
    </div>";
  }

  /**
   * Button "Neu anlegen" (UIkit)
   */
  public static function renderAddButton(Page $parent, array $childTemplates, string $adminUrl) : string {
    if (!count($childTemplates)) return '';
    $san = wire('sanitizer');
    $tpl = $childTemplates[0];
    $addUrl = rtrim($adminUrl, '/') . "/page/add/?parent_id=" . (int) $parent->id . "&template_id=" . (int) $tpl->id;
    $addUrl = $san->entities($addUrl);
    return "<div class='uk-margin-bottom'>
      <a href='{$addUrl}' class='uk-button uk-button-primary pw-panel'>
        <span uk-icon='plus'></span> Neu anlegen
      </a>
    </div>";
  }

  /**
   * Tabelle mit UIkit-Styling
   */
  public static function renderTable(array $fieldNames, PageArray $items, bool $showViewButton, string $adminUrl) : string {
    $san = wire('sanitizer');

    // Header
    $ths = "<th>Titel</th>";
    foreach ($fieldNames as $f) {
      $ths .= "<th>" . $san->entities((string) $f) . "</th>";
    }
    $ths .= "<th class='uk-text-right'>Aktionen</th>";

    // Empty State
    if(!count($items)) {
      return "<div class='uk-card uk-card-default uk-card-body'>
        <div class='uk-text-center uk-padding'>
          <span class='uk-icon' uk-icon='icon: table; ratio: 3'></span>
          <h3 class='uk-margin-small-top'>Keine Einträge gefunden</h3>
          <p class='uk-text-muted'>Versuche die Filter anzupassen oder erstelle einen neuen Eintrag.</p>
        </div>
      </div>";
    }

    $rows = '';
    foreach ($items as $p) {
      $editUrl = rtrim($adminUrl, '/') . "/page/edit/?id=" . (int) $p->id;
      $titleCell = "<td><a href='" . $san->entities($editUrl) . "'>" . $san->entities((string)$p->title) . "</a></td>";

      $dataCells = '';
      foreach ($fieldNames as $f) {
        $val = $p->get($f);

        if ($val instanceof \ProcessWire\PageArray) {
          $val = implode(', ', $val->explode('title'));
        } elseif ($val instanceof \ProcessWire\Page) {
          $val = $val->title;
        } elseif (is_array($val)) {
          $val = implode(', ', $val);
        }

        $displayVal = (string)$val;
        if(strlen($displayVal) > 100) {
          $displayVal = substr($displayVal, 0, 97) . '...';
        }

        $dataCells .= "<td>" . $san->entities($displayVal) . "</td>";
      }

      $actions = [];
      $actions[] = "<a href='" . $san->entities($editUrl) . "' class='uk-button uk-button-small uk-button-default'>Bearbeiten</a>";
      if ($showViewButton) {
        $actions[] = "<a target='_blank' href='" . $san->entities($p->url) . "' class='uk-button uk-button-small uk-button-default'>Ansehen</a>";
      }

      $rows .= "<tr>{$titleCell}{$dataCells}<td class='uk-text-right'>" . implode(' ', $actions) . "</td></tr>";
    }

    return "<div class='uk-card uk-card-default uk-margin-bottom'>
      <div class='uk-overflow-auto'>
        <table class='uk-table uk-table-divider uk-table-hover uk-table-small'>
          <thead>
            <tr>{$ths}</tr>
          </thead>
          <tbody>{$rows}</tbody>
        </table>
      </div>
    </div>";
  }

  /**
   * Pager mit UIkit-Styling
   */
  public static function renderPager(int $pageNum, int $limit, int $total, string $baseUrl, array $active) : string {
    if ($total <= $limit) return '';
    $san = wire('sanitizer');
    $pages = (int) ceil($total / $limit);

    $query = [];
    if (!empty($active['q']))    $query['q'] = $active['q'];
    if (!empty($active['by']))   $query['by'] = $active['by'];
    if (!empty($active['sort'])) $query['sort'] = $active['sort'];
    if (!empty($active['dir']))  $query['dir'] = $active['dir'];

    $makeUrl = function($pg) use ($baseUrl, $query, $san) {
      $q = $query;
      $q['pg'] = (int) $pg;
      $qs = http_build_query($q);
      $join = (strpos($baseUrl, '?') !== false) ? '&' : '?';
      return $san->entities($baseUrl . ($qs ? ($join . $qs) : ''));
    };

    $showPages = [];
    if($pages <= 7) {
      for($i = 1; $i <= $pages; $i++) $showPages[] = $i;
    } else {
      $showPages[] = 1;
      if($pageNum > 3) $showPages[] = '...';
      for($i = max(2, $pageNum - 1); $i <= min($pages - 1, $pageNum + 1); $i++) {
        $showPages[] = $i;
      }
      if($pageNum < $pages - 2) $showPages[] = '...';
      $showPages[] = $pages;
    }

    $out = "<ul class='uk-pagination uk-flex-center' uk-margin>";
    
    // Vorherige Seite
    if($pageNum > 1) {
      $out .= "<li><a href='" . $makeUrl($pageNum - 1) . "'><span uk-pagination-previous></span></a></li>";
    } else {
      $out .= "<li class='uk-disabled'><span><span uk-pagination-previous></span></span></li>";
    }

    foreach($showPages as $i) {
      if($i === '...') {
        $out .= "<li class='uk-disabled'><span>…</span></li>";
      } elseif ($i === $pageNum) {
        $out .= "<li class='uk-active'><span>{$i}</span></li>";
      } else {
        $out .= "<li><a href='" . $makeUrl($i) . "'>{$i}</a></li>";
      }
    }

    // Nächste Seite
    if($pageNum < $pages) {
      $out .= "<li><a href='" . $makeUrl($pageNum + 1) . "'><span uk-pagination-next></span></a></li>";
    } else {
      $out .= "<li class='uk-disabled'><span><span uk-pagination-next></span></span></li>";
    }

    $out .= "</ul>";

    return $out;
  }
}
