<ul>
    <li>
        <?= htmlReady($wiwiTree->institute->name) ?>
        <ul>
            <?= $this->render_partial('root/_children.php', ['nodes' => StudipStudyArea::findByParent($wiwiTree->id)]) ?>
        </ul>
    </li>
</ul>