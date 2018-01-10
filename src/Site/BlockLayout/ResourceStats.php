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
        $text = new Text("o:block[__blockIndex__][o:data][resource-classes]");

        if ($block) {
            $text->setAttribute('value', $block->dataValue('resource-classes'));
        }

        $html = '<div class="field"><div class="field-meta">';
        $html .= '<label>' . $view->translate('Resource Class Ids') . '</label>';
        $html .= '<a href="#" class="expand"></a>';
        $html .= '<div class="collapsible"><div class="field-description">' . $view->translate('Comma separated list of the resource classes (ids) to generate stats for.') . '</div></div>';
        $html .= '</div>';
        $html .= '<div class="inputs">' . $view->formRow($text) . '</div>';
        $html .= '</div>';

        return $html;
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $results = array();

        $resourceClasses = explode(',', $block->dataValue('resource-classes'));
        $site = $block->page()->site();

        foreach ($resourceClasses as $rClass) {
            $query = array();
            $query['site_id'] = $site->id();
            $query['resource_class_id'] = $rClass;

            $response = $view->api()->search('items', $query);
            $content = $response->getContent();

            if ($content) {
                $results[$content[0]->resourceClass()->uri()] = count($content);
            }
        }

        return $view->partial('common/block-layout/resource-stats', [
            'results' => $results
        ]);
    }
}
