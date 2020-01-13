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
                ... Überschrift "Kurse nach Zuordnung" bzw. "Moduledetails"
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
                <b>Bereinigungen</b>
                <br>
                <u>Aus Kursnamen</u>
                <br>
                <input name="fo_bamaBereinigen" type="checkbox" checked>
                Entferne nachfolgende Zusätze aus Kursnamen:
                <br>
                <select name="fo_bereinigung_bama[]" size="3" multiple>
                    <option selected> (Bachelor)</option>
                    <option selected> (Master)</option>
                </select>
            </div>
            <br>
            <div>
                <b>Texte und Überschriften</b> <i>(Umbrüche werden nicht berücksichtigt!)</i>
                <br>
                <u>Deckblatt: Prüfungsordnung</u>
                <br>
                <input type="text" name="fo_text_deckblattPruefungsordnung" value="Primäre Prüfungsordnung: ">
                <br>
                <br>
                <u>Deckblatt: Stand</u>
                <br>
                <input type="text" name="fo_text_deckblattStand" value="Stand: ">
                <br>
                <br>
                <u>Deckblatt: Hinweistext 1</u>
                <br>
                <textarea name="fo_text_deckblattText1">Falls Sie ältere Versionen des Modulkatalogs benötigen, setzen Sie sich bitte mit dem Dekanat der Wirtschaftswissenschaftlichen Fakultät in Verbindung (dekanat.wiwi@uni-passau.de).</textarea>
                <br>
                <br>
                <u>Deckblatt: Hinweistext 2</u>
                <br>
                <textarea name="fo_text_deckblattText2">Für alle aufgeführten Veranstaltungen des Modulkatalogs gelten die Studien- und Qualifikationsvoraussetzungen gemäß der jeweiligen Prüfungs- und Studienordnung.</textarea>
                <br>
                <br>
                <u>Inhaltsverzeichnis: Titel</u>
                <br>
                <input type="text" name="fo_text_IhvTitel" value="Inhaltsverzeichnis">
                <br>
                <br>
                <u>Modulzuordnung: Titel</u>
                <br>
                <input type="text" name="fo_text_MzoTitel" value="Modulzuordnung">
                <br>
                <br>
                <u>Kurse nach Zuordnung: Titel</u>
                <br>
                <input type="text" name="fo_text_KnZTitel" value="Kurse nach Zuordnung">
                <br>
                <br>
                <u>Kursdetails: Titel</u>
                <br>
                <input type="text" name="fo_text_KdTitel" value="Kursdetails">
                <br>
                <br>
                <u>Kursdetails: Prefix + Suffix, wenn PN in Überschrift</u>
                <br>
                <input type="text" name="fo_text_KdPNPre" size="5" value=" (PN: "> <i>«Prüfungsnummer»</i> <input type="text" name="fo_text_KdPNSuf" size="5" value=")">
                <br>
                <br>
                <u>Kursdetails: Verweis in Überschrift</u>
                <br>
                <input type="text" name="fo_text_KdUeVerwPre" size="5" value=" (siehe "> <i>«Schwerpunkt»</i> <input type="text" name="fo_text_KdUeVerwSuf" size="5" value=")">
                <br>
                <br>
                <u>Kursdetails: Verweistext auf andere Seite</u>
                <br>
                <input type="text" name="fo_text_KdTVerwPre" size="5" value="Bitte entnehmen Sie die Veranstaltungsdetails aus der unter &bdquo;"> <i>«Schwerpunkt»</i> <input type="text" name="fo_text_KdTVerwSuf" size="5" value="&ldquo; gezeigten Übersicht.">
                <br>
                <br>
                <u>Kursdetails: Prefix + Suffix für "Zeilenüberschriften" der Tabelle</u>
                <br>
                <input type="text" name="fo_text_KdZuePre" size="25" value=""> <i>«Zeilenüberschrift»</i> <input type="text" name="fo_text_KdZUeSuf" size="25" value="">
                <br>
                <br>
                <u>Kursdetails: Bitte entnehmen Sie gegebenenfalls die konkrete Dauer aus den weiteren Angaben.</u>
                <br>
                <input type="text" name="fo_text_KdTDauer" value="Bitte entnehmen Sie gegebenenfalls die konkrete Dauer aus den weiteren Angaben.">
                <br>
                <br>
                <u>Hinweise zu anderen Veranstaltungen: Titel</u>
                <br>
                <input type="text" name="fo_text_hzavTitel" value="Hinweise zu anderen Veranstaltungen">
                <br>
                <br>
                <u>Hinweise zu anderen Veranstaltungen: Überschrift: Schwerpunkt Studium Generale</u>
                <br>
                <input type="text" name="fo_text_hzavUeSSG" value="Schwerpunkt Studium Generale">
                <br>
                <br>
                <u>Hinweise zu anderen Veranstaltungen: Text: Schwerpunkt Studium Generale</u>
                <br>
                <textarea name="fo_text_hzavTSSG">Im Schwerpunkt -Studium Generale- können je nach Kapazität Angebote anderer Fakultäten gewählt werden. Die Angebote entnehmen Sie bitte aus Stud-IP.</textarea>
                <br>
                <br>
                <u>Hinweise zu anderen Veranstaltungen: Überschrift: Fremdsprachenangebot</u>
                <br>
                <input type="text" name="fo_text_hzavUeFSA" value="Fremdsprachenangebot">
                <br>
                <br>
                <u>Hinweise zu anderen Veranstaltungen: Text: Fremdsprachenangebot</u>
                <br>
                <textarea name="fo_text_hzavTFSA" rows="8">Bei den Wahlmodulen Fremdsprachen / Schlüsselkompetenzen können Sie eine Wirtschaftsfremdsprache aus dem Angebot des Sprachenzentrums der Universität Passau wählen. Das Angebot entnehmen Sie bitte aus dessen Website: http://www.sprachenzentrum.uni-passau.de/fremdsprachenausbildung/ffa/ffa-fuer-wirtschaftswissenschaftler/ Sie wählen Sprachkurse gemäß Ihren (durch Einstufungstest oder Zertifikat festgestellten) Vorkenntnissen. Prüfungsmodul ist das vollständig absolvierte Modul der jeweils höchsten erreichten Stufe. In allen Sprachen wählen Sie ab der Aufbaustufe die Fachsprache Wirtschaft. Englisch kann grundsätzlich erst ab der Aufbaustufe gewählt werden.</textarea>
                <br>
                <br>
                <u>Hinweise zu anderen Veranstaltungen: Überschrift: Schlüsselkompetenzen</u>
                <br>
                <input type="text" name="fo_text_hzavUeSK" value="Schlüsselkompetenzen">
                <br>
                <br>
                <u>Hinweise zu anderen Veranstaltungen: Text: Schlüsselkompetenzen</u>
                <br>
                <textarea name="fo_text_hzavTSK">Zusätzlich können Sie Veranstaltungen zu Schlüsselkompetenzen aus dem Angebot des Zentrums für Karriere und Kompetenzen wählen. Das Angebot entnehmen Sie bitte aus dessen Website: http://www.uni-passau.de/studium/service-und-beratung/zkk/veranstaltungen/fuer-studierende/</textarea>
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
