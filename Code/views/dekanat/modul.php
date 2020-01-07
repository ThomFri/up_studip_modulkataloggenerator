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
        <div>
            <b>Semester</b>
            <br>
            <select name="modul_semester" id="sem-drop" required="required">
                <?php foreach ($semesters as $s) : ?>
                    <?php if ($s->name===$current_semester->name) : ?>
                        <option value="<?= $s->name ?>" selected="selected"><?= htmlReady($s->name) ?></option>
                    <?php else: ?>
                        <option value="<?= $s->name ?>"><?= htmlReady($s->name) ?></option>
                    <?php endif ?>
                <?php endforeach ?>
            </select>
        </div>
        <br>
        <div>
            <b>Studiengang</b>
            <br>
            <select name="modul_major" id="major-drop" required="required" onchange="onChangeRegulation()">
                <?php for($i = 0; $i<5; $i++) : ?>
                    <?php if ($studysubjects[$i]->name === "Bachelor Business Administration and Economics") : ?>
                        <option value="<?= $studysubjects[$i]->name ?>" selected="selected"><?= htmlReady($studysubjects[$i]->name) ?></option>
                    <?php else: ?>
                        <option value="<?= $studysubjects[$i]->name ?>"><?= htmlReady($studysubjects[$i]->name) ?></option>
                    <?php endif ?>
                <?php endfor ?>
            </select>
        </div>
        <br>
        <div>
            <b>Prüfungsordnung</b>
            <br>
            <select name="modul_regulation" id="reg-drop" required="required">
                <?php foreach ($poBAE as $item) :?>
                    <option value="<?= $item ?>"><?= $item ?></option>
                <?php endforeach ?>
            </select>
        </div>


        <br>
        <br>
        <a href="#" onclick="toggle_visibility('block_fo');">Erweiterte Optionen ein-/ausblenden</a>
        <div id="block_fo" style="display: none">

            <h2><i>Erweiterte Optionen</i></h2>
            <div>
                <b>Fakultät</b>
                <br>
                <select name="modul_faculty" id="fac-drop" required="required">
                    <option value="Wirtschaftswissenschaftliche Fakultät" selected="selected">Wirtschaftswissenschaftliche Fakultät</option>

                    <?php foreach ($institutes as $s) : ?>
                        <?php if ($s->faculty!==null) : ?>
                            <option value="<?= $s->name ?>" ><?= htmlReady($s->name) ?></option>
                        <?php endif ?>
                    <?php endforeach ?>
                </select>
            </div>
            <br>
            <div>
                <b>Semesterauswahl erweitern</b>
                <br>
                <input name="modul_fullyear" type="checkbox">
                Jahres-Katalog erstellen (mit oben ausgewähltem <u>+ vorangegangenem Semester</u>)
            </div>
            <br>
            <div>
                <b>Kursnamen</b>
                <br>
                <input name="fo_bamaBereinigen" type="checkbox" checked>
                Entferne Zusätze wie "(Bachelor)" und "(Master)" aus Veranstaltungsnamen
            </div>
            <br>
            <div>
                <b>Kursgruppierung</b>
                <br>
                <input type="radio" name="fo_aufteilung" value="schwerpunkt" checked>
                Nach Schwerpunkten
                <br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <input name="fo_veranstaltungsVerweis" type="checkbox">
                <i>Bei Kursen die mehrere Schwerpunkte haben nur beim ersten Auftreten Kursseite ausgeben und ansonsten nur drauf verweisen</i>
                <br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <input name="fo_veranstaltungsVerweisTOC" type="checkbox">
                <i>Diesen Verweis zusätzlich im Inhaltsverzeichnis kenntlich machen</i>
                <br>
                <input type="radio" name="fo_aufteilung" value="alle">
                Keine Gruppierung (-> einfach alle Kurse auflisten)
            </div>
            <br>
            <div>
                <b>Kurssortierung innerhalb Gruppe</b>
                <br>
                <input type="radio" name="fo_sort1" value="name" checked>
                Nach Veranstaltungs<u>name</u>, alphabetisch aufsteigend
                <br>
                <input type="radio" name="fo_sort1" value="num">
                Nach Veranstaltungs<u>nummer</u>, aufsteigend

            </div>
            <br>
            <div>
                <b>Kursseite</b>
                <br>
                <input name="fo_kursseiteVeranstaltungsnummer" type="checkbox" checked>
                Titel enthält Veranstaltungsnummer (und damit auch das Inhaltsverzeichnis)
                <br>
                <input name="fo_kursseitePruefungsnummer" type="checkbox">
                Titel enthält Prüfungsnummer (und damit auch das Inhaltsverzeichnis)

            </div>
            <br>
            <div>
                <b>Veranstaltungen in nicht deutscher Sprache</b>
                <br>
                <input name="fo_lang1" type="checkbox">
                Veranstaltungsseite auf englisch ausgeben <i>(-> momentan nur erste Spalte!)</i>

            </div>
            <br>
            <div>
                <b>Umbruch nach...</b>
                <br>
                <input name="fo_umbruch_deckblatt" type="checkbox" checked>
                ... Deckblatt
                <br>
                <input name="fo_umbruch_TOC" type="checkbox" checked>
                ... Inhaltsverzeichnis
                <br>
                <input name="fo_umbruch_modulzuordnungstabellen" type="checkbox" checked>
                ... Allen Modulzuordnungstabellen
                <br>
                <input name="fo_umbruch_moduleNachZuordnung" type="checkbox" checked>
                ... Überschrift "Module nach Zuordnung" bzw. "Moduledetails"
                <br>
                <input name="fo_umbruch_schwerpunktGruppe" type="checkbox" checked>
                ... Schwerpunktgruppe <i>(falls oben Gruppierung nach Schwerpunkten gewählt)</i>
                <br>
                <input name="fo_umbruch_kursseite" type="checkbox" checked>
                .. Jeder Kursseite

            </div>
            <br>
            <div>
                <b>Zu berücksichtigende Schwerpunkte</b>
                <br>
                <select name="fo_relevanteSchwerpunkte[]" size="15" multiple>
                    <option selected>Basismodule</option>
                    <option selected>Wahlmodule</option>
                    <option selected>Economics</option>
                    <option selected>Wirtschaftsinformatik</option>
                    <option selected>Accounting, Finance and Taxation</option>
                    <option selected>Management, Innovation, Marketing</option>
                    <option selected>Informatik / Mathematik</option>
                    <option selected>Wahlpflichtmodule</option>
                    <option selected>Seminar aus Wirtschaftsinformatik</option>
                    <option selected>Pflichtmodule</option>
                    <option selected>Wahlmodule BWL/VWL</option>
                    <option selected>Wahlmodule Wirtschaftsinformatik/Informatik</option>
                    <option selected>Schwerpunktnote</option>
                    <option selected>Methoden</option>
                    <option selected>Accounting, Finance and Taxation</option>
                    <option selected>International Management and Marketing</option>
                    <option selected>Wirtschaftsinformatik / Information Systems</option>
                    <option selected>Modulgruppe A: Core Courses</option>
                    <option selected>Modulgruppe B: Advanced Methods</option>
                    <option selected>Modulgruppe C: Global Economy, International Trade, and Finance</option>
                    <option selected>Modulgruppe D: Governance, Institutions and Development</option>
                    <option selected>Modulgruppe E: Business</option>
                    <option selected>Statistische und theoretische Grundlagen</option>
                    <option selected>Globalization, Geography and the Multinational Firm</option>
                    <option selected>International Finance</option>
                    <option selected>Governance, Institutions and Anticorruption</option>
                    <option selected>Wirtschaftswissenschaftliche Grundlagen</option>
                    <option selected>Wirtschaftsinformatik/ Informations Systems</option>
                    <option selected>Interdisziplinäres Vertiefungsangebot</option>
                    <option selected>Interdisziplinärer Block</option>
                    <option></option>
                </select>
            </div>
            <br>
            <div>
                <b>Zu berücksichtigende Kurstypen</b>
                <br>
                <select name="fo_relevanteKurstypen[]" size="5" multiple>
                    <option selected>Vorlesung</option>
                    <option selected>Seminar</option>
                    <option selected>Praktikum</option>
                    <option>Blockveranstaltung</option>
                </select>
            </div>
            <br>
            <div>
                <b>Log und Debug</b>
                <br>
                <input name="fo_log" type="checkbox">
                Logdatei ausgeben um einen Überblick über mögliche Fehler oder Unvollständigkeiten zu erhalten
                <br>
                <input name="fo_debug" type="checkbox">
                DEBUG-Felder in Dokument schreiben

            </div>
        </div>
        <br>
        <br>


        <footer>
            <?= Studip\Button::createAccept("DOCX generieren", "modul_docx") ?>
            <!--<?= Studip\Button::createAccept("PDF generieren", "modul_pdf") ?>-->
        </footer>
    </section>
</form>
