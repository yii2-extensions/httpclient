<?php

declare(strict_types=1);

/**
 * @var int $queryCount
 * @var int $queryTime
 * @var yii\data\ArrayDataProvider $dataProvider
 * @var yii\httpclient\debug\HttpClientPanel $panel
 * @var yii\httpclient\debug\SearchModel $searchModel
 * @var yii\web\View $this
 */
use yii\helpers\Html;
use yii\grid\GridView;

?>
<h1><?= $panel->getName(); ?> Requests</h1>

<?php

echo GridView::widget([
    'dataProvider' => $dataProvider,
    'id' => 'db-panel-detailed-grid',
    'options' => ['class' => 'detail-grid-view table-responsive'],
    'filterModel' => $searchModel,
    'filterUrl' => $panel->getUrl(),
    'columns' => [
        ['class' => 'yii\grid\SerialColumn'],
        [
            'attribute' => 'seq',
            'label' => 'Time',
            'value' => function ($data) {
                $timeInSeconds = $data['timestamp'] / 1000;
                $millisecondsDiff = (int) (($timeInSeconds - (int) $timeInSeconds) * 1000);

                return date('H:i:s.', (int) $timeInSeconds) . sprintf('%03d', $millisecondsDiff);
            },
            'headerOptions' => [
                'class' => 'sort-numerical'
            ]
        ],
        [
            'attribute' => 'duration',
            'value' => fn($data) => sprintf('%.1f ms', $data['duration']),
            'options' => [
                'width' => '10%',
            ],
            'headerOptions' => [
                'class' => 'sort-numerical'
            ]
        ],
        [
            'attribute' => 'type',
            'value' => fn($data) => Html::encode($data['type']),
            'filter' => $panel->getTypes(),
        ],
        [
            'attribute' => 'method',
            'value' => fn($data) => Html::encode(mb_strtoupper((string) $data['method'], 'utf8')),
            'filter' => $panel->getMethods(),
        ],
        [
            'attribute' => 'request',
            'value' => function ($data) {
                $query = Html::encode($data['request']);

                if (!empty($data['trace'])) {
                    $query .= Html::ul($data['trace'], [
                        'class' => 'trace',
                        'item' => fn($trace) => "<li>{$trace['file']} ({$trace['line']})</li>",
                    ]);
                }

                if ($data['type'] !== 'batch') {
                    $query .= Html::tag(
                        'div',
                        implode('<br>', [
                            Html::a(
                                '&gt;&gt; Execute',
                                [
                                    'request-execute',
                                    'seq' => $data['seq'],
                                    'tag' => Yii::$app->controller->summary['tag']
                                ],
                                ['target' => '_blank'],
                            ),
                            Html::a(
                                '&gt;&gt; Pass Through',
                                [
                                    'request-execute',
                                    'seq' => $data['seq'],
                                    'tag' => Yii::$app->controller->summary['tag'],
                                    'passthru' => true
                                ],
                                ['target' => '_blank'],
                            ),
                        ]),
                        ['class' => 'db-explain']
                    );
                }

                return $query;
            },
            'format' => 'raw',
            'options' => [
                'width' => '60%',
            ],
        ]
    ],
]);
?>
