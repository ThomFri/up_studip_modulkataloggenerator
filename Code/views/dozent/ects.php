<?php

use Studip\Button;

?>
<h3>Eigene ECTS-Liste erzeugen</h3>
<form class="default" method="GET"  action="<?= $controller->url_for('submit/index') ?>" >
    <section>
        <label>
            <h4>Lehrende(r):</h4>
            <?= htmlReady($user->vorname) . " " . htmlReady($user->nachname) ?>
        </label>
        <label>
            Semester
            <select name="ects_semester" id="sem-drop" required="required">
                <option value="all">alle Semester</option>
                <?php foreach ($semesters as $s) : ?>
                    <?php if ($s->name===$current_semester->name) : ?>
                        <option value="<?= $s->name ?>" selected="selected"><?= htmlReady($s->name) ?></option>
                    <?php else: ?>
                        <option value="<?= $s->name ?>"><?= htmlReady($s->name) ?></option>
                    <?php endif ?>
                <?php endforeach ?>
            </select>
        </label>
        <!--
        <label title="Lassen Sie sich eine Logdatei zur Erstellung des Dokuments ausgeben um einen Überblick über mögliche Fehler oder Unvollständigkeiten zu erhalten" >
            Logdatei ausgeben
            <input name="ects_log" type="checkbox">
        </label>
        -->
        <br>
        <footer>
            <?= Studip\Button::createAccept("DOCX generieren", 'dozent_ects_docx') ?>
            <!--<?= Studip\Button::createAccept("PDF generieren", "dozent_ects_pdf") ?>-->
        </footer>
    </section>
</form>