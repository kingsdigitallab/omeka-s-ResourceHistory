<?php
namespace ResourceHistory\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Zend\View\Renderer\PhpRenderer;

class ResourceStats extends AbstractBlockLayout
{
    public function getLabel()
    {
        print('hello');
        return 'Resource Stats'; // @translate
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        return $this->breakTypeSelect($view, $site, $block);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $breakType = $block->dataValue('break_type');

        return "<div class='break $breakType'></div>";
    }

    public function breakTypeSelect(PhpRenderer $view, SiteRepresentation $site,
        SitePageBlockRepresentation $block = null
    ) {
        $options = [
            'transparent' => 'Transparent', // @translate
            'opaque' => 'Opaque', // @translate
        ];
        $breakType = $block ? $block->dataValue('break_type', 'transparent') : 'transparent';

        $select = new Select('o:block[__blockIndex__][o:data][break_type]');
        $select->setValueOptions($options)->setValue($breakType);

        $html = '<div class="field">';
        $html .= '<div class="field-meta"><label>' . $view->translate('Break type') . '</label></div>';
        $html .= '<div class="inputs">' . $view->formSelect($select) . '</div>';
        $html .= '</div>';
        return $html;
    }

    // public function form(
    //     PhpRenderer $view,
    //     SiteRepresentation $site,
    //     SitePageRepresentation $page = null,
    //     SitePageBlockRepresentation $block = null
    // ) {
    //     $text = new Text("o:block[__blockIndex__][o:data][query]");
    //
    //     if ($block) {
    //         $text->setAttribute('value', $block->dataValue('query'));
    //     }
    //
    //     $html = '<div class="field"><div class="field-meta">';
    //     $html .= '<label>' . $view->translate('Resource Type Ids') . '</label>';
    //     $html .= '<a href="#" class="expand"></a>';
    //     $html .= '<div class="collapsible"><div class="field-description">' . $view->translate('Comma separated list of the resource classes (ids) to generate stats for.') . '</div></div>';
    //
    //     return $html;
    // }
    //
    // public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    // {
    //     parse_str($block->dataValue('query'), $query);
    //     $resourceClassIds = explode(',', $query);
    //
    //     $site = $block->page()->site();
    //     $query['site_id'] = $site->id();
    //     $query['resource_class_id'] = $resourceClassIds[0];
    //
    //     $response = $view->api()->search('items', $query);
    //     $items = count($response->getContent());
    //
    //     return $view->partial('common/block-layout/resource-stats', [
    //         'items' => $items
    //     ]);
    // }
}
