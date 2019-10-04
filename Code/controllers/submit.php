<?php

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\ListItem;

include __DIR__.'/../composer/vendor/autoload.php';

/**
 * @property  array modulTabelle zweidimensionales Array, in dem an erster Stelle im
 * inneren Array der Modulname steht und an weiterer Stelle alle unter dieses Modul fallende Veranstaltungen,
 * dient zur Erstellung der Modulzuordnung nach der Gliederung
 * @property array kurse Array, in dem alle schon enthaltenen Kursobjekte gespeichert sind,
 * dient zur Überprüfung auf Mehrfacheinträge
 */
class SubmitController extends AuthenticatedController {

    /**
     * Aktionen und Einstellungen, werden vor jedem Seitenaufruf aufgerufen
     */
    public function before_filter(&$action, &$args){

        $this->plugin = $this->dispatcher->plugin;
        $this->flash = Trails_Flash::instance();

        $this->set_layout(Request::isXhr() ? null : $GLOBALS['template_factory']->open('layouts/base'));

        $this->modulTabelle = array();
        $this->kurse = array();
    }

    /**
     * Wird aufgerufen, wenn ein "... erzeugen"-Button gedrückt wurde
     * Holt sich die Eingaben aus dem HTML-Form zu dem Zeitpunkt des Buttonclicks
     * Verarbeitet diese und erstellt das gewünschte Dokument
     */
    public function index_action(){
        //neue Logdatei erstellen und Infotext anzeigen
        unlink($GLOBALS['TMP_PATH'].'/log.log');
        Log::set('logdatei', $GLOBALS['TMP_PATH'].'/log.log');
        Log::info_logdatei("
            Logdatei der Erstellung des Katalogs: \n
            Hier werden zu allen Einträgen des Katalogs Informationen angezeigt, sollte etwas fehlen, unvollständig oder fehlerhaft sein. \n
            Legende: \n
            [NOTICE] Eher unbedeutende Felder sind unvollständig \n
            [WARNING] Bei wichtigen Feldern fehlt Information oder die Ausgabe könnte fehlerhaft sein \n
            [ALERT] Bei äußerst wichtigen Feldern fehlt Information oder die Ausgabe könnte fehlerhaft sein. Diese Felder sollten in jedem Fall händisch nachgebessert werden");
        //---

        //Request::get() holt die Inhalte aus den jeweiligen HTML-Forms der view-Klassen
        $auftrag = "";
        $profName = "";
        if (Request::submitted("ects_docx") || Request::submitted("ects_pdf")) {
            $auftrag = "ects";
            $profName = Request::get("ects_prof");
        } elseif (Request::submitted("dozent_ects_docx") || Request::submitted("dozent_ects_pdf")) {
            $auftrag = "ects";
            $profName = get_username();
        } elseif (Request::submitted("modul_docx") || Request::submitted("modul_pdf")) {
            $auftrag = "modul";
        }

        $datei = "";
        if (Request::submitted("ects_docx") || Request::submitted("modul_docx") ||
            Request::submitted("dozent_ects_docx"))
            $datei = "docx";
        elseif (Request::submitted("ects_pdf") || Request::submitted("modul_pdf") ||
            Request::submitted("dozent_ects_pdf"))
            $datei = "pdf";

        $inputArray = array();
        if ($auftrag === "ects") {
            $inputArray = array(
                "semester" => Request::get("ects_semester"),
                "fakultaet" => Request::get("ects_faculty"),
                "studiengang" => "",
                "po" => "",
                "datei" => $datei,
                "auftrag" => $auftrag,
                "profUsername" => $profName,
                "log" => Request::get("ects_log")); //inputArray['log'] = "on" oder ""
        } elseif ($auftrag === "modul") {
            $inputArray = array(
                "semester" => Request::get("modul_semester"),
                "fakultaet" => Request::get("modul_faculty"),
                "studiengang" => Request::get("modul_major"),
                "po" => Request::get("modul_regulation"),
                "datei" => $datei,
                "auftrag" => $auftrag,
                "profUsername" => "",
                "log" => Request::get("modul_log")); //inputArray['log'] = "on" oder ""
        }

        $file = "document";
        //PhpWord ist eine Hilfsklasse zum Erstellen der Word-Files
        $phpWord = new PhpWord();
        $phpWord->getCompatibility()->setOoxmlVersion(15); //setzt die Kompatibilität auf Word2013
        Settings::setOutputEscapingEnabled(true);
        $phpWord->getSettings()->setHideGrammaticalErrors(true);
        $phpWord->getSettings()->setHideSpellingErrors(true);
        //DomPDF wird zur Erzeugung von PDFs verwendet -> für spätere Versionen
        $options = new Options();
        $options->setChroot($GLOBALS['TMP_PATH']);
        $dompdf = new Dompdf($options);

        $headerSection = $phpWord->addSection(); //Titel des Dokuments und Gliederung
        $headerStyle = array('name' => 'Tahoma', 'size' => 16, 'bold' => true);
        $mainSection = $phpWord->addSection(array('breakType' => 'continuous')); //Inhalt des Dokuments
        $titleStyle = array('name' => 'Arial', 'size' => 12, 'bold' => true);
        $centerStyle = array('alignment' => Jc::CENTER);
        $tableStyle = array('cellMargin' => 40);

        if ($inputArray['auftrag'] === 'modul') { //Modulkatalog erstellen
            $headerSection->addText("Modulkatalog für " . $inputArray['studiengang'] .
                " (" . $inputArray['po'] . ")" . " im " . $inputArray['semester'], $headerStyle, $centerStyle);
            $headerSection->addText("Enthaltene Module:", array('size' => 14, 'underline' => Font::UNDERLINE_SINGLE));
            $count = 1;
            //Hinweistext zu nicht enthaltenden Modulen:
            $endSection = $phpWord->addSection(); //Hinweis auf letzter Seite des Dokuments
            $endInfoStyle = array('size' => 12, 'underline' => Font::UNDERLINE_SINGLE);
            $endSection->addText("Hinweise zu anderen Veranstaltungen:", array('size' => 14, 'underline' => Font::UNDERLINE_SINGLE));
            $endSection->addText("Schwerpunkt Studium Generale", $endInfoStyle);
            $endSection->addText("Im Schwerpunkt -Studium Generale- können je nach Kapazität Angebote anderer Fakultäten gewählt werden. Die Angebote entnehmen Sie bitte aus Stud-IP.");
            $endSection->addText("Fremdsprachenangebot", $endInfoStyle);
            $endSection->addText("Bei den Wahlmodulen Fremdsprachen / Schlüsselkompetenzen können Sie eine Wirtschaftsfremdsprache aus dem Angebot des Sprachenzentrums der Universität Passau wählen. Das Angebot entnehmen Sie bitte aus dessen Website: http://www.sprachenzentrum.uni-passau.de/fremdsprachenausbildung/ffa/ffa-fuer-wirtschaftswissenschaftler/ Sie wählen Sprachkurse gemäß Ihren (durch Einstufungstest oder Zertifikat festgestellten) Vorkenntnissen. Prüfungsmodul ist das vollständig absolvierte Modul der jeweils höchsten erreichten Stufe. In allen Sprachen wählen Sie ab der Aufbaustufe die Fachsprache Wirtschaft. Englisch kann grundsätzlich erst ab der Aufbaustufe gewählt werden. ");
            $endSection->addText("Schlüsselkompetenzen", $endInfoStyle);
            $endSection->addText("Zusätzlich können Sie Veranstaltungen zu Schlüsselkompetenzen aus dem Angebot des Zentrums für Karriere und Kompetenzen wählen. Das Angebot entnehmen Sie bitte aus dessen Website: http://www.uni-passau.de/studium/service-und-beratung/zkk/veranstaltungen/fuer-studierende/");
            //---
            $file = 'Modulkatalog_' . $inputArray['semester'] . '_' .
                $inputArray['studiengang'];

            /**
             * Vorgehensweise: Iteration durch den Fakultätsbaum und Abgleich mit den Eingaben (inputArray)
             * wird das gesuchte Element gefunden gehen wir ein Schritt in den Baum hinein
             * das wird so lange gemacht, bis man bei den gesuchten Kursen angelangt ist
             * Es wird durch folgendes iteriert: (siehe Veranstaltungsbaum im Plugin)
             * Studiengänge (subjectTree)
             * Prüfungsordnungsversionen (poTree)
             * Module (module)
             * Fächer/Veranstaltungen (faecher)
             */
            $instituteTree = StudipStudyArea::findOnebyStudip_object_id(Institute::findOneByName($inputArray['fakultaet'])->id);

            $sCnt = 0;
            $subjectTree = StudipStudyArea::findByParent($instituteTree->id);
            foreach ($subjectTree as $s) {
                if ($inputArray['studiengang'] === $s->name)
                    break;
                $sCnt++;
            }
            $tmp = StudipStudyArea::findByParent($subjectTree[$sCnt]->id);
            $poTree = array();
            $bool = true;
            foreach ($tmp as $t) {
                if ($t->name === "Studien- und Prüfungsordnung") {
                    $poTree = StudipStudyArea::findByParent($t->id);
                    $bool = false;
                }
            }
            if ($bool) {
                $poTree = $tmp;
            }

            $p = 0;
            foreach ($poTree as $s) {
                if ($inputArray['po'] === $s->name)
                    break;
                $p++;
            }

            $tmp = StudipStudyArea::findByParent($poTree[$p]->id);
            $module = array();
            $studLevel = "";
            foreach ($tmp as $t) {
                if ($t->name === "Bachelornote" || $t->name === "Masternote") {
                    $module = StudipStudyArea::findByParent($t->id);
                    $studLevel = $t->name;
                    break;
                }
            }

            //alle Module der verschiedenen Prüfungsordnungen und Studiengänge, die relevante Kurse enthalten
            //ausgeschlossen sind Fremdsprachen, Studium Generale, Seminare des ZKK (siehe Hinweise auf letzter Seite des fertigen Dokuments)
            $relevanteModule = array(
                //Bachelor
                "Basismodule",
                "Wahlmodule",
                "Economics",
                "Wirtschaftsinformatik",
                "Accounting, Finance and Taxation",
                "Management, Innovation, Marketing",
                "Informatik / Mathematik",
                "Wahlpflichtmodule",
                "Seminar aus Wirtschaftsinformatik",
                "Pflichtmodule",
                "Wahlmodule BWL/VWL",
                "Wahlmodule Wirtschaftsinformatik/Informatik",
                "Schwerpunktnote", //weitere Abstufung notwendig (BWI WS2015)
                //Master
                "Methoden",
                "Accounting, Finance and Taxation", //weitere Abstufung notwendig (MBA Version1)
                "International Management and Marketing", //weitere Abstufung notwendig (MBA Version1)
                "Wirtschaftsinformatik / Information Systems", //weitere Abstufung notwendig (MBA Version1)
                "Modulgruppe A: Core Courses",
                "Modulgruppe B: Advanced Methods",
                "Modulgruppe C: Global Economy, International Trade, and Finance",
                "Modulgruppe D: Governance, Institutions and Development",
                "Modulgruppe E: Business",
                "Statistische und theoretische Grundlagen",
                "Globalization, Geography and the Multinational Firm",
                "International Finance",
                "Governance, Institutions and Anticorruption",
                "Wirtschaftswissenschaftliche Grundlagen",
                "Wirtschaftsinformatik/ Informations Systems",
                "Interdisziplinäres Vertiefungsangebot",
                "Interdisziplinärer Block");

            $relevanteVaTypen = array("Vorlesung", "Seminar"); //sollten weitere Typen im Modulkatalog gewünscht sein, können diese hier hinzugefügt werden

            foreach ($module as $m) {
                if (in_array($m->name, $relevanteModule)) {
                    $faecher = StudipStudyArea::findByParent($m->id);
                    if ($m->name === "Schwerpunktnote") { //BWI WS2015: Schwerpunkte - Abstufung notwendig
                        foreach ($faecher as $f) {
                            $nextTreeStep = StudipStudyArea::findByParent($f->id);
                            foreach ($nextTreeStep as $n) {
                                $courses = $n->courses;
                                foreach ($courses as $cours) {
                                    if ($cours->start_semester->name === $inputArray['semester'] && //nach ausgewähltem Semester filtern
                                        in_array($cours->getSemType()['name'], $relevanteVaTypen) && //nach Vorlesungen und Seminaren filtern
                                        $f->name !== "Studium Generale"){ //Studium Generale nicht anzeigen
                                        $headerSection->addText($count .". ".$this->encodeText($cours->name));
                                        $mainSection->addText($count++ .". ".$this->encodeText($cours->name), $titleStyle, $centerStyle);
                                        $table = $mainSection->addTable($tableStyle);
                                        $this->addTableToDoc($cours, $table, $f->name);
                                        $this->modulUebersicht($cours, $f->name);
                                        $mainSection->addPageBreak();

                                    }
                                }
                            }
                        }
                    }
                    elseif ($studLevel === "Masternote" && (
                        $m->name === "Accounting, Finance and Taxation" ||
                        $m->name === "International Management and Marketing" ||
                        $m->name === "Wirtschaftsinformatik / Information Systems")){ //MBA Version1: Abstufung in Grundlagen und Vertiefung
                        foreach ($faecher as $f) {
                            $nextTreeStep = StudipStudyArea::findByParent($f->id);
                            foreach ($nextTreeStep as $n) {
                                $courses = $n->courses;
                                foreach ($courses as $cours) {
                                    if ($cours->start_semester->name === $inputArray['semester'] && //nach ausgewähltem Semester filtern
                                        in_array($cours->getSemType()['name'], $relevanteVaTypen)){ //nach Vorlesungen und Seminaren filtern
                                        $headerSection->addText($count .". ".$this->encodeText($cours->name));
                                        $mainSection->addText($count++ .". ".$this->encodeText($cours->name), $titleStyle, $centerStyle);
                                        $table = $mainSection->addTable($tableStyle);
                                        $modName = $m->name . " - " . $f->name;
                                        $this->addTableToDoc($cours, $table, $modName);
                                        $this->modulUebersicht($cours, $modName);
                                        $mainSection->addPageBreak();
                                    }
                                }
                            }

                        }

                    }
                    else { //Rest - keine Abstufung
                        foreach ($faecher as $f) {
                            $courses = $f->courses;
                            foreach ($courses as $cours) {
                                if ($cours->start_semester->name === $inputArray['semester'] && //nach ausgewähltem Semester filtern
                                    in_array($cours->getSemType()['name'], $relevanteVaTypen)){ //nach Vorlesungen und Seminaren filtern
                                    $headerSection->addText($count .". ".$this->encodeText($cours->name));
                                    $mainSection->addText($count++ .". ".$this->encodeText($cours->name), $titleStyle, $centerStyle);
                                    $table = $mainSection->addTable($tableStyle);
                                    $this->addTableToDoc($cours, $table, $m->name);
                                    $this->modulUebersicht($cours, $m->name);
                                    $mainSection->addPageBreak();
                                }
                            }
                        }

                    }

                }

            }
            $headerSection->addPageBreak(); //erstellt Seitenumbruch nach der Gliederung
            //Modulzuordnung ins Dokument schreiben
            $headerSection->addText("Modulzuordnung:", array('size' => 14, 'underline' => Font::UNDERLINE_SINGLE));
            foreach ($this->modulTabelle as $modTab) {
                $modTable = $headerSection->addTable(array(
                    'borderColor' => '000000',
                    'borderSize' => 4,
                    'cellMargin' => 20
                ));

                $modTable->addRow();
                $modTable->addCell()->addText($this->encodeText($modTab[0]), $titleStyle, $centerStyle);

                $modTable->addRow();
                $cell = $modTable->addCell();
                for ($j = 1; $j < sizeof($modTab); $j++) {
                    $cell->addListItem($this->encodeText($modTab[$j]), ListItem::TYPE_BULLET_FILLED);
                }

                $headerSection->addTextBreak();
            }
            $headerSection->addPageBreak(); //erstellt Seitenumbruch nach der Modulzuordnung
        }

        elseif ($inputArray['auftrag'] === 'ects') { //ECTS-Liste erstellen
            $user = User::findByUsername($inputArray['profUsername']);
            $file = 'ECTS-Liste_' . $inputArray['semester'] . '_' .
                $inputArray['profUsername'];
            $table = $mainSection->addTable(array(
                'borderColor' => '000000',
                'borderSize'  => 4,
                'cellMargin'  => 80
            ));

            $alleKurse = CourseMember::findByUser($user->id);

            if($inputArray['semester'] === 'all'){ //alle Semester
                $headerSection->addText("ECTS-Liste für " . $user->getFullName() . " (alle Semester)", $headerStyle, $centerStyle);

                $table->addRow();
                $table->addCell()->addText("Name", $titleStyle);
                $table->addCell()->addText("Semester", $titleStyle);
                $table->addCell()->addText("Typ", $titleStyle);
                $table->addCell()->addText("ECTS", $titleStyle);

                foreach ($alleKurse as $kurs) {
                    $table->addRow();
                    $table->addCell()->addText($this->encodeText($kurs->course_name));
                    $table->addCell()->addText($this->encodeText($kurs->course->start_semester->name));
                    $table->addCell()->addText($this->encodeText($kurs->course->getSemType()['name']));
                    $table->addCell()->addText($this->encodeText($kurs->course->ects));
                }
            }
            else { //ein bestimmtes Semester

                $headerSection->addText("ECTS-Liste für " . $user->getFullName() . " (" . $inputArray['semester'] . ")", $headerStyle, $centerStyle);

                $table->addRow();
                $table->addCell()->addText("Name", $titleStyle);
                $table->addCell()->addText("Typ", $titleStyle);
                $table->addCell()->addText("ECTS", $titleStyle);

                foreach ($alleKurse as $kurs) {
                    if ($kurs->course->start_semester->name === $inputArray['semester']) {
                        $table->addRow();
                        $table->addCell()->addText($this->encodeText($kurs->course_name));
                        $table->addCell()->addText($this->encodeText($kurs->course->getSemType()['name']));
                        $table->addCell()->addText($this->encodeText($kurs->course->ects));
                    }
                }
            }
        }

        $html_ending = '.html';
        $pdf_ending = '.pdf';
        $docx_ending = '.docx';
        $zip_ending = '.zip';

        //Ausgabe des Dokuments, abhängig von Datei- und Log-Auswahl
        if ($inputArray['datei'] === 'docx'&&$inputArray['log'] !== 'on') {//DOCX und kein Log
            try {
                $xmlWriter = IOFactory::createWriter($phpWord, 'Word2007');

                header("Content-Description: File Transfer");
                header('Content-Disposition: attachment; filename="' . $file . $docx_ending . '"');
                header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document; charset=UTF-8');
                header('Content-Transfer-Encoding: binary');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Expires: 0');

                $xmlWriter->save("php://output");

            } catch (\PhpOffice\PhpWord\Exception\Exception $e) {} //wird geworfen, wenn falscher 'name' übergeben wird
        }
        elseif ($inputArray['datei'] === 'docx'&&$inputArray['log'] === 'on'){//DOCX und Log
            try {
                $xmlWriter = IOFactory::createWriter($phpWord, 'Word2007');
                $xmlWriter->save($GLOBALS['TMP_PATH'].'/'.$file.$docx_ending);

                $zip = new ZipArchive();
                unlink($GLOBALS['TMP_PATH'].'/files.zip');
                $zip->open($GLOBALS['TMP_PATH'].'/files.zip', ZipArchive::CREATE);
                $zip->addFile($GLOBALS['TMP_PATH'].'/'.$file.$docx_ending, $file.$docx_ending);
                $zip->addFile($GLOBALS['TMP_PATH'].'/log.log', 'log.log');
                $zip->close();

                header('Content-Description: File Transfer');
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="'.$file.'_inklLog'.$zip_ending.'"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');

                readfile($GLOBALS['TMP_PATH'].'/files.zip');

            } catch (\PhpOffice\PhpWord\Exception\Exception $e) {
            }//wird geworfen, wenn falscher 'name' in createWriter() übergeben wird

        }

        /**
         * In aktueller Version (1.0) werden keine PDFs vom Plugin erzeugt
         * Dieser Part kann für nächste Versionen genutzt werden
         * es muss nur der PDF-Button in den Views aktiv gesetzt werden und das Design des PDFs angepasst werden
         */
        elseif ($inputArray['datei'] === 'pdf' && $inputArray['log'] !== 'on') {//PDF und kein Log
            try {
                $xmlWriter = IOFactory::createWriter($phpWord, 'HTML');

                $xmlWriter->save($GLOBALS['TMP_PATH'] . '/document' . $html_ending);
                try {
                    $dompdf->loadHtmlFile($GLOBALS['TMP_PATH'] . '/document' . $html_ending);
                } catch (\Dompdf\Exception $e) {
                }
                $dompdf->render();
                $dompdf->stream($file . $pdf_ending);

            } catch (\PhpOffice\PhpWord\Exception\Exception $e) {
            } //wird geworfen, wenn falscher 'name' übergeben wird
        }
        elseif ($inputArray['datei'] === 'pdf' && $inputArray['log'] === 'on') {//PDF und Log
            try {
                $xmlWriter = IOFactory::createWriter($phpWord, 'HTML');
                $xmlWriter->save($GLOBALS['TMP_PATH'] . '/document' . $html_ending);

                try {
                    $dompdf->loadHtmlFile($GLOBALS['TMP_PATH'] . '/document' . $html_ending);
                } catch (\Dompdf\Exception $e) {
                }
                $dompdf->render();
                file_put_contents($GLOBALS['TMP_PATH'].$file.$pdf_ending, $dompdf->output());

                $zip = new ZipArchive();
                unlink($GLOBALS['TMP_PATH'].'/files.zip');
                $zip->open($GLOBALS['TMP_PATH'].'/files.zip', ZipArchive::CREATE);
                $zip->addFile($GLOBALS['TMP_PATH'].'/'.$file.$pdf_ending, $file.$pdf_ending);
                $zip->addFile($GLOBALS['TMP_PATH'].'/log.log', 'log.log');
                $zip->close();

                header('Content-Description: File Transfer');
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="'.$file.'_inklLog'.$zip_ending.'"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');

                readfile($GLOBALS['TMP_PATH'].'/files.zip');
            } catch (\PhpOffice\PhpWord\Exception\Exception $e) {
            } //wird geworfen, wenn falscher 'name' übergeben wird
        }

        exit;
    }

    /**
     * Formatiert den Eingabetext aus der Datenbank von StudIP, um Umlaute und Sonderzeichen anzeigen zu können
     * @param $text String unformatierter Text aus StudIP
     * @return string Text mit darstellbaren Umlauten und Sonderzeichen
     */
    public function encodeText($text){
        return html_entity_decode(htmlentities($text));
    }

    public function url_for($to = '')
    {
        $args = func_get_args();

        // find params
        $params = array();
        if (is_array(end($args))) {
            $params = array_pop($args);
        }

        // urlencode all but the first argument
        $args = array_map("urlencode", $args);
        $args[0] = $to;

        return PluginEngine::getURL($this->plugin, $params, join("/", $args));
    }

    /**
     * @param $table Table Tabellenobjekt, in welches geschrieben wird
     * @param $titel String Titel des Eintrags in die Tabelle
     * @param $inhalt String Inhalt des Eintrags in die Tabelle
     */
    public function addTextToTable($table, $titel, $inhalt){
        $table->addRow();
        $table->addCell()->addText($titel);
        $table->addCell()->addText($this->encodeText($inhalt));
    }

    /**
     * Befüllt die Tabelle in der die Modulübersicht gespeichert ist
     * @param $cours Course Kursobjekt
     * @param $modul String Modulzuordnung
     */
    public function modulUebersicht($cours, $modul){
        $bool = true;
        foreach ($this->modulTabelle as $modTab) {
            if(in_array($modul, $modTab))
               $bool = false;
        }
        if($bool){
            array_push($this->modulTabelle, array($modul));
        }

        for ($i = 0; $i<sizeof($this->modulTabelle); $i++){
            for($j = 0; $j<sizeof($this->modulTabelle[$i]); $j++){
                if($this->modulTabelle[$i][$j] === $modul){
                    array_push($this->modulTabelle[$i], $cours->name);
                }
            }
        }
    }

    /**
     * Schreibt Kursdetails in die angehängte Tabelle
     * @param $cours Course Kursobjekt
     * @param $table Table Tabellenobjekt, in welches geschrieben wird
     * @param $modul String Modulzuordnung
     */
    public function addTableToDoc($cours, $table, $modul)
    {
        $this->addTextToTable($table, "Untertitel:", $cours->untertitel);
        $this->addTextToTable($table, "Veranstaltungsnr.:", $cours->veranstaltungsnummer);
        $this->addTextToTable($table, "Typ:", $cours->getSemType()['name']);
        $this->addTextToTable($table, "Modulzuordnung:", $modul);
        $this->addTextToTable($table, "Lehrende:", $this->dozenten($cours));
        $this->addTextToTable($table, "Heimateinrichtung:", Institute::find($cours->institut_id)->name);
        $this->addTextToTable($table, "Art:", $cours->art);
        $this->addTextToTable($table, "Beschreibung:", $cours->beschreibung);
        $this->addTextToTable($table, "ECTS:", $cours->ects);
        $this->addTextToTable($table, "Teilnehmer:", $cours->teilnehmer);
        $this->addTextToTable($table, "Vorrausetzungen:", $cours->vorrausetzungen);
        $this->addTextToTable($table, "Lernorganisation:", $cours->lernorga);
        $this->addTextToTable($table, "Leistungsnachweis:", $cours->leistungsnachweis);
        foreach ($cours->datafields as $datafield)
            if($datafield->name==="SWS"||$datafield->name==="Turnus"||
                $datafield->name==="Literatur"||$datafield->name==="Qualifikationsziele")
                $this->addTextToTable($table, $datafield->name.":", $datafield->content);
        $this->addTextToTable($table, "Sonstiges:", $cours->sonstiges);

        //Logdatei:
        //Notice
        //Untertitel, Art, Sonstiges könnte hier rein;
        //ist allerdings fast IMMER leer... würde den Log überfüllen und unübersichtlich machen

        //Warning
        $ectsArray = str_split($cours->ects);
        $ectsBool = false;
        foreach ($ectsArray as $item)
            if (!is_numeric($item))
                $ectsBool = true;
        if($ectsBool)
            Log::warn_logdatei("Kurs " . $cours->name . ": ECTS besteht nicht nur aus Ziffern");
        if($this->encodeText($this->dozenten($cours))==="")
            Log::warn_logdatei("Kurs " . $cours->name . ": Lehrende leer");
        if($this->encodeText(Institute::find($cours->institut_id)->name)==="")
            Log::warn_logdatei("Kurs " . $cours->name . ": Heimateinrichtung leer");
        if($this->encodeText($cours->teilnehmer)==="")
            Log::warn_logdatei("Kurs " . $cours->name . ": Teilnehmer leer");
        if($this->encodeText($cours->vorrausetzungen)==="")
            Log::warn_logdatei("Kurs " . $cours->name . ": Vorrausetzungen leer");
        if($this->encodeText($cours->lernorga)==="")
            Log::warn_logdatei("Kurs " . $cours->name . ": Lernorganisation leer");
        if($this->encodeText($cours->leistungsnachweis)==="")
            Log::warn_logdatei("Kurs " . $cours->name . ": Leistungsnachweis leer");

        //Alert
        if($this->encodeText($cours->ects)==="")
            Log::alert_logdatei("Kurs " . $cours->name . ": ECTS leer");
        if($this->encodeText($cours->veranstaltungsnummer)==="")
            Log::alert_logdatei("Kurs " . $cours->name . ": Veranstaltungsnummer leer");
        if($this->encodeText($cours->beschreibung)==="")
            Log::alert_logdatei("Kurs " . $cours->name . ": Beschreibung leer");

        //To Do für nächste Version: Automatische Verwaltung von Mehrfacheinträgen
        if(!in_array($cours->name, $this->kurse))
            array_push($this->kurse, $cours->name);
        else
            Log::alert_logdatei("Kurs " . $cours->name . ": Mehrfach enthalten, da zu mehreren Modulen zugeordnet! Bitte händisch den zweiten Eintrag löschen und das Modul beim ersten Eintrag ergänzen");

    }

    /**
     * @param $cours Course Kursobjekt
     * @return string alle Lehrende dieses Kurses, mit Komma getrennt
     */
    public function dozenten($cours){
        $l = 0;
        foreach ($cours->members as $member)
            if($member->status==="dozent")
                $l++;

        $lehrende = "";
        foreach ($cours->members as $member){
            if($member->status==="dozent") {
                if ($l == 1)
                    $lehrende .= User::find($member->user_id)->getFullName();
                else
                    $lehrende .= User::find($member->user_id)->getFullName() . ", ";
                $l--;
            }
        }
        return $lehrende;
    }
}
