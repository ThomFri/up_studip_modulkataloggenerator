<?php foreach ($nodes as $one) : ?>
    <li>
        <?= htmlReady($one->name) ?>
        <?php if ($one->_children) : ?>
            <ul>
            <?= $this->render_partial('root/_children', ['nodes' => StudipStudyArea::findByParent($one->id)]) ?>
            </ul>

        <?php endif ?>

    </li>
<?php endforeach ?>
