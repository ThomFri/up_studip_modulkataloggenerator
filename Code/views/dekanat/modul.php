<?php

use Studip\Button;

?>

<h3>Modulkatalog erzeugen</h3>
<form name="modul_dek" class="default" method="POST" action="<?= $controller->url_for('submit/index')?>" onload="populateRegulation()">
    <section>
        <label>
            Semester
            <select name="modul_semester" id="sem-drop" required="required">
                <?php foreach ($semesters as $s) : ?>
                    <?php if ($s->name===$current_semester->name) : ?>
                        <option value="<?= $s->name ?>" selected="selected"><?= htmlReady($s->name) ?></option>
                    <?php else: ?>
                        <option value="<?= $s->name ?>"><?= htmlReady($s->name) ?></option>
                    <?php endif ?>
                <?php endforeach ?>
            </select>
        </label>
        <label title="Vorangegangenes Semester mit aufnahmen (ganzjährige Sicht)" >
            <input name="modul_fullyear" type="checkbox">
            Jahres-Katalog erstellen (mit obigem + vorangegangenem Semester)
        </label>
        <label>
            Fakultät
            <select name="modul_faculty" id="fac-drop" required="required">
                <option value="Wirtschaftswissenschaftliche Fakultät" selected="selected">Wirtschaftswissenschaftliche Fakultät</option>

                <?php foreach ($institutes as $s) : ?>
                    <?php if ($s->faculty!==null) : ?>
                        <option value="<?= $s->name ?>" ><?= htmlReady($s->name) ?></option>
                    <?php endif ?>
                <?php endforeach ?>
            </select>
        </label>
        <label>
            Studiengang
            <select name="modul_major" id="major-drop" required="required" onchange="onChangeRegulation()">
                <?php for($i = 0; $i<5; $i++) : ?>
                    <?php if ($studysubjects[$i]->name === "Bachelor Business Administration and Economics") : ?>
                        <option value="<?= $studysubjects[$i]->name ?>" selected="selected"><?= htmlReady($studysubjects[$i]->name) ?></option>
                    <?php else: ?>
                        <option value="<?= $studysubjects[$i]->name ?>"><?= htmlReady($studysubjects[$i]->name) ?></option>
                    <?php endif ?>
                <?php endfor ?>
            </select>
        </label>
        <label>
            Prüfungsordnung
            <select name="modul_regulation" id="reg-drop" required="required">
                <?php foreach ($poBAE as $item) :?>
                    <option value="<?= $item ?>"><?= $item ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label title="Lassen Sie sich eine Logdatei zur Erstellung des Dokuments ausgeben um einen Überblick über mögliche Fehler oder Unvollständigkeiten zu erhalten" >
            Logdatei ausgeben
            <input name="modul_log" type="checkbox">
        </label>
        <br>
        <footer>
            <?= Studip\Button::createAccept("DOCX generieren", "modul_docx") ?>
            <!--<?= Studip\Button::createAccept("PDF generieren", "modul_pdf") ?>-->
        </footer>
    </section>
</form>
