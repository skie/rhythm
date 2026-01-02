<?php
/**
 * Cache Widget
 *
 * This template fetches its own data and then extends the base widget
 * to handle the presentation.
 *
 * @var \App\View\AppView $this
 * @var mixed $config
 * @var object $widget
 * @var mixed $widgetName
 */
$this->set('widget', $widget);
$this->set('config', $config);
$this->set('widgetName', $widgetName);

$period = (int) $this->getRequest()->getQuery('period', 60);
$options = ['period' => $period];
$sort = $this->get('sort');
if ($sort !== null) {
    $options['sort'] = $sort;
}
$data = $widget->getData($options);
$this->set('data', $data);

$this->extend('Rhythm.widgets/widget_base');

$this->start('widget_body');

if (!isset($data['hit_rate']) || ($data['hits'] === 0 && $data['misses'] === 0)) {
    echo $this->element('Rhythm.components/widget_placeholder', ['message' => 'No cache usage recorded.']);
} else {
    $summaryStats = [
        ['label' => 'Hits', 'value' => number_format($data['hits'] ?? 0)],
        ['label' => 'Misses', 'value' => number_format($data['misses'] ?? 0)],
        ['label' => 'Hit Rate', 'value' => number_format($data['hit_rate'] ?? 0, 2) . '%'],
    ];

    $cacheKeyInteractions = $data['cacheKeyInteractions'] ?? [];
?>
    <div class="widget-content">
        <?= $this->Rhythm->summaryStats($summaryStats) ?>

        <?php if (!empty($cacheKeyInteractions)): ?>
            <div class="rhythm-mt-lg">
                <?php
                $tableHead = ['Key', 'Hits', 'Misses', 'Hit Rate'];
                $tableBody = [];

                foreach ($cacheKeyInteractions as $interaction) {
                    $keyHits = $interaction->hits ?? 0;
                    $keyMisses = $interaction->misses ?? 0;
                    $keyTotal = $keyHits + $keyMisses;
                    $keyHitRate = $keyTotal > 0 ? round(($keyHits / $keyTotal) * 100, 2) : 0;

                    $decodedKey = rawurldecode($interaction->key ?? '');

                    $tableBody[] = [
                        '<code class="rhythm-text-xs" title="' . h($decodedKey) . '">' . h($decodedKey) . '</code>',
                        number_format($keyHits),
                        number_format($keyMisses),
                        number_format($keyHitRate, 2) . '%',
                    ];
                }
                ?>
                <?= $this->element('Rhythm.components/table', [
                    'head' => $tableHead,
                    'body' => $tableBody,
                    'class' => 'rhythm-mt-md',
                ]) ?>

                <?php if (count($cacheKeyInteractions) >= 100): ?>
                    <div class="rhythm-mt-sm rhythm-text-xs rhythm-text-secondary rhythm-text-center">
                        Limited to 100 entries
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php
}
$this->end();
?>

