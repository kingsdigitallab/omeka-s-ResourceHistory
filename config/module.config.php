<?php
namespace ResourceHistory;

return [
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            __DIR__ . '/../src/Entity',
        ],
    ],
    'block_layouts' => [
        'invokables' => [
            'resourceStats' => Site\BlockLayout\ResourceStats::class,
        ],
    ],
];
