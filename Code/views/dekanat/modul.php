<?php

use Studip\Button;

?>


<script type="text/javascript">
    <!--
    function toggle_visibility(id) {
        var e = document.getElementById(id);
        if(e.style.display == 'block')
            e.style.display = 'none';
        else
            e.style.display = 'block';
    }
    //-->
</script>



<h2>Modulkatalog erzeugen</h2>
<form name="modul_dek" class="default" method="POST" action="<?= $controller->url_for('submit/index')?>" onload="populateRegulation()">
    <section>
        <label>
            <b>Semester</b>
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

        <label>
            <b>Fakultät</b>
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
            <b>Studiengang</b>
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
            <b>Prüfungsordnung</b>
            <select name="modul_regulation" id="reg-drop" required="required">
                <?php foreach ($poBAE as $item) :?>
                    <option value="<?= $item ?>"><?= $item ?></option>
                <?php endforeach ?>
            </select>
        </label>


        <br>
        <br>
        <a href="#" onclick="toggle_visibility('block_fo');">Erweiterte Optionen ein-/ausblenden</a>
        <div id="block_fo" style="display: none">


            <h2><i>Erweiterte Optionen</i></h2>

            <br>
            <b>Semesterauswahl erweitern</b>
            <label title="Vorangegangenes Semester mit aufnahmen (ganzjährige Sicht)" >
                <input name="modul_fullyear" type="checkbox">
                Jahres-Katalog erstellen (mit obigem + vorangegangenem Semester)
            </label>


            <br>
            <b>Kursgruppierung</b>
            <br>
            <input type="radio" name="fo_aufteilung" value="schwerpunkt" checked>
            Nach Schwerpunkten
            <br>
            <input type="radio" name="fo_aufteilung" value="alle">
            Keine Gruppierung (Einfach alle Kurse auflisten)



            <br>
            <br>
            <b>Kurssortierung innerhalb Gruppe</b>
            <br>
            <input type="radio" name="fo_sort1" value="name" checked>
            Nach Veranstaltungs<u>name</u>, alphabetisch aufsteigend
            <br>
            <input type="radio" name="fo_sort1" value="num">
            Nach Veranstaltungs<u>nummer</u>, aufsteigend


            <br>
            <br>
            <b>Log und Debug</b>
            <label title="Lassen Sie sich eine Logdatei zur Erstellung des Dokuments ausgeben um einen Überblick über mögliche Fehler oder Unvollständigkeiten zu erhalten" >
                <input name="fo_log" type="checkbox">
                Logdatei ausgeben
            </label>
        </div>
        <br>
        <br>


        <footer>
            <?= Studip\Button::createAccept("DOCX generieren", "modul_docx") ?>
            <!--<?= Studip\Button::createAccept("PDF generieren", "modul_pdf") ?>-->
        </footer>
    </section>
</form>
