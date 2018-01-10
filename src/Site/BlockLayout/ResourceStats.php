<?php
namespace ResourceHistory\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Zend\Form\Element\Text;
use Zend\View\Renderer\PhpRenderer;

class ResourceStats extends AbstractBlockLayout
{
    public function getLabel()
    {
        return 'Resource Stats'; // @translate
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $page = null,
        SitePageBlockRepresentation $block = null
    ) {
        $text = new Text("o:block[__blockIndex__][o:data][query]");
        $resource = new Text("o:block[__blockIndex__][o:data][resource]");
        $label = new Text("o:block[__blockIndex__][o:data][label]");

        if ($block) {
            $text->setAttribute('value', $block->dataValue('query'));
            $resource->setAttribute('value', $block->dataValue('resource'));
            $label->setAttribute('value', $block->dataValue('label'));
        }

        $html = '<div class="field"><div class="field-meta">';
        $html .= '<label>' . $view->translate('Query') . '</label>';
        $html .= '<a href="#" class="expand"></a>';
        $html .= '<div class="collapsible"><div class="field-description">' . $view->translate('Display resource counts for this query, for example to get a count of all the persons the query should be `resource_class_id=1304`.') . '</div></div>';
        $html .= '</div>';
        $html .= '<div class="inputs">' . $view->formRow($text) . '</div>';
        $html .= '</div>';

        $html .= '<div class="field"><div class="field-meta">';
        $html .= '<label>' . $view->translate('Resource') . '</label>';
        $html .= '<a href="#" class="expand"></a><div class="collapsible"><div class="field-description">' . $view->translate('Resources to search on: items, media, etc.') . '</div></div>';
        $html .= '</div>';
        $html .= '<div class="inputs">' . $view->formRow($resource) . '</div>';
        $html .= '</div>';

        $html .= '<div class="field"><div class="field-meta">';
        $html .= '<label>' . $view->translate('Label') . '</label>';
        $html .= '<a href="#" class="expand"></a><div class="collapsible"><div class="field-description">' . $view->translate('Label for the results') . '</div></div>';
        $html .= '</div>';
        $html .= '<div class="inputs">' . $view->formRow($label) . '</div>';
        $html .= '</div>';

        return $html;
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $results = array();

        parse_str($block->dataValue('query'), $query);

        // commenting site_id for now because most of the images don't seem to
        // be associated with a site
        // $site = $block->page()->site();
        // $query['site_id'] = $site->id();

        $resource = $block->dataValue('resource');

        $response = $view->api()->search($resource, $query);
        $content = $response->getContent();

        if ($content) {
            $results[$content[0]->resourceClass()->uri()] = count($content);
        }

        return $view->partial('common/block-layout/resource-stats', [
            'results' => $results,
            'label' => $block->dataValue('label')
        ]);
    }
}
