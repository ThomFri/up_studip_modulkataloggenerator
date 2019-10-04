<?php

use Studip\Button;

?>

<h3>ECTS-Liste erzeugen</h3>
<form class="default" method="GET"  action="<?= $controller->url_for('submit/index') ?>" >
    <section>
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
        <label>
            Fakultät
            <select name="ects_faculty" id="fac-drop" required="required">
                <option value="Wirtschaftswissenschaftliche Fakultät" selected="selected">Wirtschaftswissenschaftliche Fakultät</option>
            </select>
        </label>
        <label>
            Lehrstuhl
            <select name="ects_lehrstuhl" id="lehrstuhl-drop" required="required" onchange="onChangeProfs()">
                <?php foreach ($sub_wiwi as $s) : ?>
                    <?php if (strpos($s->name, 'Lehr') !== false) : ?>
                        <?php if ($s->name === "Lehreinheit für ABWL") :?>
                            <option value="<?= $s->institut_id ?>" selected="selected"><?= htmlReady($s->name) ?></option>
                        <?php else : ?>
                            <option value="<?= $s->institut_id ?>"><?= htmlReady($s->name) ?></option>
                        <?php endif ?>
                    <?php endif ?>
                <?php endforeach ?>
            </select>
        </label>
        <label>
            Lehrende(r)
            <select name="ects_prof" id="prof-drop" required="required">
                <?php foreach ($abwl_members as $s) : ?>
                    <?php if($s->username!=null&&$s->username!=="unipassau_nn") : ?>
                        <option value="<?= $s->username ?>" selected="selected"><?= htmlReady($s->vorname) . " " . htmlReady($s->nachname) ?></option>
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
            <?= Studip\Button::createAccept("DOCX generieren", 'ects_docx') ?>
            <!--<?= Studip\Button::createAccept("PDF generieren", "ects_pdf") ?>-->
        </footer>
    </section>
</form>