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
# use PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(false);

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
     * Globale Variablen
     * =================
     */
    private $tabSchwerpunktKurse;  //speichert alle Schwerpunkte (array pos 0) mit dazugehörigen Kursen ab (array pos 1, ...)
    private $tabKursSchwerpunkte;  //speichert alle Kurse (array pos 0) mit dazugehörigen Schwerpunkten ab (array pos 1, ...)
    private $inputArray;           //für auslesen der HTML-Form
    private $log_infotext;
    private $custom_styles;
    private $sort_schwerpunkte_vorgabe;
    private $name_bachelorMaster;

    /**
     * Aktionen und Einstellungen, werden vor jedem Seitenaufruf aufgerufen
     * ====================================================================
     */
    public function before_filter(&$action, &$args){
        $this->plugin = $this->dispatcher->plugin;
        $this->flash = Trails_Flash::instance();

        $this->set_layout(Request::isXhr() ? null : $GLOBALS['template_factory']->open('layouts/base'));

        //Globale Variablen initialisieren
        $this->modulTabelle = array();
        $this->tabSchwerpunktKurse = array();
        $this->tabKursSchwerpunkte = array();
        $this->kurse = array();

        //Globale Variablen setzen (feste Einstellungen)
        $this->log_infotext = "
            Logdatei der Erstellung des Katalogs: \n
            Hier werden zu allen Einträgen des Katalogs Informationen angezeigt, sollte etwas fehlen, unvollständig oder fehlerhaft sein. \n
            Legende: \n
            [NOTICE] Eher unbedeutende Felder sind unvollständig \n
            [WARNING] Bei wichtigen Feldern fehlt Information oder die Ausgabe könnte fehlerhaft sein \n
            [ALERT] Bei äußerst wichtigen Feldern fehlt Information oder die Ausgabe könnte fehlerhaft sein. Diese Felder sollten in jedem Fall händisch nachgebessert werden"
            ;
        $this->custom_styles['headerStyle'] = array('name' => 'Tahoma', 'size' => 16, 'bold' => true);
        $this->custom_styles['footertext']  = 'Seite {PAGE} von {NUMPAGES}';
        $this->custom_styles['fontStyle12'] = array('spaceAfter' => 60, 'size' => 12);
        $this->custom_styles['fontStyle11'] = array('spaceAfter' => 60, 'size' => 11);
        $this->custom_styles['allign_center1'] = array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER);
        $this->custom_styles['styleFrontMatterHeading'] = array('name' => 'Arial', 'size' => 28, 'bold' => true,  'alignment' => Jc::CENTER);
        $this->custom_styles['styleFrontMatterText'] = array('name' => 'Arial', 'size' => 15, 'bold' => false, 'alignment' => Jc::CENTER);
        $this->custom_styles['titleStyle'] = array('name' => 'Arial', 'size' => 12, 'bold' => true);
        $this->custom_styles['centerStyle'] = array('alignment' => Jc::CENTER);
        $this->custom_styles['leftStyle'] = array('alignment' => Jc::LEFT);
        $this->custom_styles['tableStyle'] = array('cellMargin' => 40, 'borderSize' => 1);
        $this->custom_styles['endInfoStyle'] = array('size' => 12, 'underline' => Font::UNDERLINE_SINGLE);
        $this->custom_styles['modTableTabName'] = 'modTableTab';
        $this->custom_styles['modTableTabStyle'] = array('tabs' => array(new \PhpOffice\PhpWord\Style\Tab('left', 7000)));
        $this->custom_styles['PlistName'] = 'P-listStyle';
        $this->custom_styles['PlistStyle'] = array('hanging'=>0, 'left'=>0, 'lineHeight'=>1, 'color'=>'FFFAE3');
        $this->custom_styles['logoDir'] = __DIR__.'/../src/logo01.png';
        $this->custom_styles['logoAllign'] = array('width' => 300, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER);
        $this->custom_styles['tocIndent'] = array('indent' => 100);
        //$this->custom_styles[''] = ;
        $this->sort_schwerpunkte_vorgabe = array("Basismodule", "Wahlpflichtmodule");
        $this->name_bachelorMaster = array("Bachelor", "Master");
        $this->name_bachelorMasterSuffix = "-Studiengang"; //nur für Deckblatt
        $this->name_inhaltsverzeichnis = 'Inhaltsverzeichnis';
        $this->relevanteModule = array(
            //alle Module der verschiedenen Prüfungsordnungen und Studiengänge, die relevante Kurse enthalten
            //ausgeschlossen sind Fremdsprachen, Studium Generale, Seminare des ZKK (siehe Hinweise auf letzter Seite des fertigen Dokuments)

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
        $this->relevanteVeranstaltungsTypen = array(
            //sollten weitere Typen im Modulkatalog gewünscht sein, können diese hier hinzugefügt werden
            "Vorlesung",
            "Seminar",
            "Praktikum"//,
            //  "Blockveranstaltung"
        );
    }


    /**
     * Wird aufgerufen, wenn ein Erzeugen-Button gedrückt wurde
     * ========================================================
     *
     * Holt sich die Eingaben aus dem HTML-Form zu dem Zeitpunkt des Buttonclicks
     * Verarbeitet diese und erstellt das gewünschte Dokument
     */
    public function index_action(){
        /**
         * neue Logdatei erstellen und Infotext anzeigen
         */
        unlink($GLOBALS['TMP_PATH'].'/log.log');
        Log::set('logdatei', $GLOBALS['TMP_PATH'].'/log.log');
        Log::info_logdatei($this->log_infotext);


        /**
         * Inhalte der HTML-Form abfragen
         * (Request::get() holt die Inhalte aus den jeweiligen HTML-Forms der view-Klassen)
         */

        //Welcher Auftrag?
        //================
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


        //inputArray befüllen
        //===================
            $this->inputArray = array();
            if ($auftrag === "ects") {
                $this->inputArray = array(
                    "semester" => Request::get("ects_semester"),
                    "fakultaet" => Request::get("ects_faculty"),
                    "studiengang" => "",
                    "po" => "",
                    "datei" => $datei,
                    "auftrag" => $auftrag,
                    "profUsername" => $profName,
                    "log" => Request::get("ects_log")); //inputArray['log'] = "on" oder ""
            } elseif ($auftrag === "modul") {
                $this->inputArray = array(
                    "semester" => Request::get("modul_semester"),
                    "fullyear" => Request::get("modul_fullyear"),
                    "fakultaet" => Request::get("modul_faculty"),
                    "studiengang" => Request::get("modul_major"),
                    "po" => Request::get("modul_regulation"),
                    "datei" => $datei,
                    "auftrag" => $auftrag,
                    "profUsername" => "",
                    "aufteilung" => Request::get("fo_aufteilung"),
                    "sorttype" => Request::get("fo_sort1"),
                    "sprachenkonvertierung" => Request::get("fo_lang1"),
                    "log" => Request::get("fo_log")); //inputArray['log'] = "on" oder ""
            }


        /**
         * PhpWord initialisieren
         * ======================
         *
         * Hilfsklasse zum Erstellen der Word-Files
         * TODO: omPDF wird zur Erzeugung von PDFs verwendet -> für spätere Versionen
         * TODO: Alternativ -> https://stackoverflow.com/questions/33084148/generate-pdf-from-docx-generated-by-phpword
         */

        $file = "document";
        $phpWord = new PhpWord();
        $phpWord->getCompatibility()->setOoxmlVersion(15); //setzt die Kompatibilität auf Word2013
        Settings::setOutputEscapingEnabled(true);
        $phpWord->getSettings()->setHideGrammaticalErrors(true);
        $phpWord->getSettings()->setHideSpellingErrors(true);

        $options = new Options();
        $options->setChroot($GLOBALS['TMP_PATH']);
        $dompdf = new Dompdf($options);

        //Sections

            //Titel des Dokuments ALT: und Gliederung
                $headerSection = $phpWord->addSection();

                //Table of Contents
                    $tocSection = $phpWord->addSection(); //Inhaltsverzeichnisse

                    //Fußzeile
                        $tocFooter = $tocSection->addFooter();
                        $tocFooter->addPreserveText($this->custom_styles['footertext'], null, $this->custom_styles['allign_center1']);

                //Vor Inhalt, aber nach TOC
                    $preContentSection = $phpWord->addSection(); //Zuordnungen

            //Inhaltssektion
                $mainSection = $phpWord->addSection(array('breakType' => 'continuous')); //Inhalt des Dokuments

                //Fußzeile
                    $mainFooter = $mainSection->addFooter();
                    $mainFooter->addPreserveText($this->custom_styles['footertext'], null, $this->custom_styles['allign_center1']);



        //Styles hinzufügen
            $phpWord->addParagraphStyle($this->custom_styles['modTableTabName'], $this->custom_styles['modTableTabStyle']);
            $phpWord->addParagraphStyle($this->custom_styles['PlistName'], $this->custom_styles['PlistStyle']);
            $phpWord->addTitleStyle(0, $this->custom_styles['titleStyle']);
            $phpWord->addTitleStyle(1, $this->custom_styles['headerStyle'], $this->custom_styles['centerStyle']);
            $phpWord->addTitleStyle(2, $this->custom_styles['headerStyle'], $this->custom_styles['centerStyle']);
            $phpWord->addTitleStyle(3, $this->custom_styles['headerStyle'], $this->custom_styles['centerStyle']);


        if ($this->inputArray['auftrag'] === 'modul') { //Modulkatalog erstellen
            /**
             * Allgemeine Vorbereitungen
             * =========================
             */

                    //Studiengangsname
                        $tmp_studiengangName = $this->inputArray['studiengang'];
                        $tmp_studiengangUntertitel = "";
                        $tmp_bachelorMaster = ""; //Bachelor oder Master
                        $bool_bachelorMasterGefunden = false;

                        foreach($this->name_bachelorMaster as $current_name) {
                            if(strpos($tmp_studiengangName, $current_name) !== false) {
                                $bool_bachelorMasterGefunden = true;
                                $tmp_bachelorMaster = $current_name;
                                $tmp_studiengangName = str_replace($current_name." ", "", $tmp_studiengangName);

                                //Sonderfälle
                                if($current_name == $this->name_bachelorMaster[0] && $tmp_studiengangName == 'Wirtschaftsinformatik' && $this->inputArray['po'] == 'Version WS 2015'){
                                    $tmp_studiengangUntertitel = "(Information Systems)";
                                }

                                break; //increase speed
                            }
                        }

                    //Name Winter/Sommer
                        $semesterName = $this->inputArray['semester'];
                        $semesterName = str_replace("WiSe", "WS", $semesterName);
                        $semesterName = str_replace("SoSe", "SS", $semesterName);


                    //Ganzjahreskatalog?
                        $zutreffendeSemester = array($this->inputArray['semester']);
                        if($this->inputArray['fullyear'] === 'on') { //Ganzjahreskatalog
                            $semesterPrev = "";
                            if(substr($this->inputArray['semester'],0,4) == "WiSe") {
                                $tmpNum = substr($this->inputArray['semester'],5,2);
                                $semesterPrev = "SoSe ".$tmpNum;
                            }
                            elseif(substr($this->inputArray['semester'],0,4) == "WiSe") {
                                $tmpNum = intval(substr($this->inputArray['semester'],5,2))+1;
                                if(strlen($tmpNum)==1)
                                {
                                    $tmpNum = "0".$tmpNum;
                                }
                                $semesterPrev = "WiSe ".$tmpNum;
                            }

                            array_push($zutreffendeSemester, $semesterPrev);
                        }
                        else {} //nur ausgewähltes Semester

                    //Dateiname
                        $file = 'Modulkatalog_';
                        foreach($zutreffendeSemester as $current_semester) {
                            $file = $file.$current_semester."_";
                        }
                        $file = $file.$this->inputArray['studiengang'];






            /**
             * Ausgabe
             * =======
             */

                //Deckblatt
                //=========

                    //$headerSection->addTitle("Modulkatalog für " . $inputArray['studiengang'] .
                    //    " (" . $inputArray['po'] . ")" . " im " . $inputArray['semester'],0);
                    //$headerSection->addText("Enthaltene Module:", array('size' => 14, 'underline' => Font::UNDERLINE_SINGLE));
                    //$headerSection->addText("\t".$inputArray['studiengang'], $this->custom_styles['modTableTabName']);

                    //Logo
                        $headerSection->addImage($this->custom_styles['logoDir'], $this->custom_styles['logoAllign']);

                    //Bachelor / Master - Studiengang
                        if($bool_bachelorMasterGefunden) {
                            $headerSection->addText($tmp_bachelorMaster.$this->name_bachelorMasterSuffix, $this->custom_styles['styleFrontMatterHeading']);
                            $headerSection->addText("", $this->custom_styles['styleFrontMatterHeading']);
                        }

                    //Studiengang XYZ
                        $headerSection->addText($tmp_studiengangName, $this->custom_styles['styleFrontMatterHeading']);

                    //Unteritel des Studiengangs
                        if($tmp_studiengangUntertitel !== "") {
                            $headerSection->addText($tmp_studiengangUntertitel, $this->custom_styles['styleFrontMatterHeading']);
                        }
                        else {
                            $headerSection->addText("", $this->custom_styles['styleFrontMatterHeading']);
                        }
                        $headerSection->addText("", $this->custom_styles['styleFrontMatterHeading']);


                    //Modulkatalog
                        $headerSection->addText("Modulkatalog", $this->custom_styles['styleFrontMatterHeading']);
                        $headerSection->addText("", $this->custom_styles['styleFrontMatterHeading']);

                    //Semester
                        $headerSection->addText($semesterName, $this->custom_styles['styleFrontMatterHeading']);
                        $headerSection->addText("", $this->custom_styles['styleFrontMatterText']);

                    //Zusatzangaben
                        $headerSection->addText("Primäre Prüfungsordnung: ".$this->inputArray['po'], $this->custom_styles['styleFrontMatterText']);
                        $headerSection->addText("Stand: ".date('d. F Y'), $this->custom_styles['styleFrontMatterText']);
                        $headerSection->addText("", $this->custom_styles['styleFrontMatterText']);
                        $headerSection->addText("", $this->custom_styles['styleFrontMatterText']);

                    //Infos
                        $headerSection->addText("Falls Sie ältere Versionen des Modulkatalogs benötigen, setzen Sie sich bitte mit dem Dekanat der Wirtschaftswissenschaftlichen Fakultät in Verbindung (dekanat.wiwi@uni-passau.de).", $this->custom_styles['styleFrontMatterText']);
                        $headerSection->addText("", $this->custom_styles['styleFrontMatterText']);
                        $headerSection->addText("Für alle aufgeführten Veranstaltungen des Modulkatalogs gelten die Studien- und Qualifikationsvoraussetzungen gemäß der jeweiligen Prüfungs- und Studienordnung.", $this->custom_styles['styleFrontMatterText']);


                //Inhaltsverzeichnis
                //==================
            $tocSection->addTitle($this->name_inhaltsverzeichnis);
            //$tmpStyle123 = array('indentation' => array('left' => 540, 'right' => 120), 'bold' => true);
            //$tocSection->setStyle($tmpStyle123);
            $tocSection->addTOC($this->custom_styles['fontStyle11'], $this->custom_styles['tocIndent']);

            //$count = 1;




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
            $instituteTree = StudipStudyArea::findOnebyStudip_object_id(Institute::findOneByName($this->inputArray['fakultaet'])->id);

            $sCnt = 0;
            $subjectTree = StudipStudyArea::findByParent($instituteTree->id);
            foreach ($subjectTree as $s) {
                if ($this->inputArray['studiengang'] === $s->name)
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
                if ($this->inputArray['po'] === $s->name)
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


            $relevanteModule = $this->relevanteModule;
            $relevanteVaTypen = $this->relevanteVeranstaltungsTypen;

            foreach ($module as $m) {
                if (in_array($m->name, $relevanteModule)) {
                    $faecher = StudipStudyArea::findByParent($m->id);
                    if ($m->name === "Schwerpunktnote") { //BWI WS2015: Schwerpunkte - Abstufung notwendig
                        foreach ($faecher as $f) {
                            $nextTreeStep = StudipStudyArea::findByParent($f->id);
                            foreach ($nextTreeStep as $n) {
                                $courses = $n->courses;
                                foreach ($courses as $cours) {
                                    if (in_array($cours->start_semester->name, $zutreffendeSemester) &&
                                        in_array($cours->getSemType()['name'], $relevanteVaTypen) && //nach Vorlesungen und Seminaren filtern
                                        $f->name !== "Studium Generale") { //Studium Generale nicht anzeigen
                                            $this->modulEinordnen($cours, $f->name);
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
                                    if (in_array($cours->start_semester->name, $zutreffendeSemester) &&
                                        in_array($cours->getSemType()['name'], $relevanteVaTypen)){ //nach Vorlesungen und Seminaren filtern
                                            $modName = $m->name . " - " . $f->name;
                                            $this->modulEinordnen($cours, $modName);
                                    }
                                }
                            }
                        }
                    }
                    else { //Rest - keine Abstufung
                        foreach ($faecher as $f) {
                            $courses = $f->courses;
                            foreach ($courses as $cours) {
                                if (in_array($cours->start_semester->name, $zutreffendeSemester) &&
                                    in_array($cours->getSemType()['name'], $relevanteVaTypen)) { //nach Vorlesungen und Seminaren filtern
                                        $this->modulEinordnen($cours, $m->name);
                                }
                            }
                        }
                    }
                }
            }
            $headerSection->addPageBreak(); //erstellt Seitenumbruch nach der Gliederung


            //Modulzuordnung ins Dokument schreiben
            $currentSection = $tocSection;

            $currentSection->addPageBreak();
            $currentSection->addTitle("Modulzuordnung", 1); //array('size' => 14, 'underline' => Font::UNDERLINE_SINGLE));

            $this->modulzuordnungUndKurseOrdnen($this->sort_schwerpunkte_vorgabe, $this->inputArray['sorttype']);


            foreach ($this->tabSchwerpunktKurse as $modTab) {

                $currentSection->addText($modTab[0], $this->custom_styles['titleStyle'], $this->custom_styles['leftStyle']);

                $modTable = $currentSection->addTable(array(
                    'borderColor' => '000000',
                    'borderSize' => 4,
                    'cellMargin' => 40
                ));

                for ($j = 1; $j < sizeof($modTab); $j++) {
                    $modTable->addRow();
                    $cell = $modTable->addCell();
                    $cours = $modTab[$j];
                    $kursname = $cours->name;

                    $removeBAMA = true;

                    if($removeBAMA)
                        $kursname = str_replace(" (Bachelor)", "", str_replace(" (Master)", "", $kursname));

                    $cell->addText($this->encodeText($cours->veranstaltungsnummer."\t".$kursname));
                    //$cell->addListItem($this->encodeText($modTab[$j]), ListItem::TYPE_BULLET_FILLED);
                }

                $tocSection->addTextBreak();
            }
            $tocSection->addPageBreak(); //erstellt Seitenumbruch nach der Modulzuordnung



            if($this->inputArray['aufteilung'] == "schwerpunkt") {

                $mainSection->addTitle("Module nach Zuordnung", 1);
                $mainSection->addPageBreak();

                //Module sortiert schreiben
                $schonGezeigtStattAusgabe = true;
                for ($i = 0; $i < sizeof($this->tabSchwerpunktKurse); $i++) {//$this->modulOrdnungsTabelle as $modTab) { //Loop über Schwerpunkte
                    $modTab = $this->tabSchwerpunktKurse[$i];

                    //Schwerpunktseite schreiben
                    $this->modulGruppeSchreiben($modTab[0], $mainSection);

                    //Enthaltene Veranstaltungen schreiben
                    for ($j = 1; $j < sizeof($modTab); $j++) { //Loop über Veranstaltungen
                        $nurVerweis = false;
                        $verweisAuf = "";

                        if ($schonGezeigtStattAusgabe) {
                            for ($k = 0; $k < $i; $k++) {
                                for ($l = 1; $l < sizeof($this->tabSchwerpunktKurse[$k]); $l++) {
                                    if ($this->tabSchwerpunktKurse[$k][$l]->veranstaltungsnummer == $modTab[$j]->veranstaltungsnummer) {
                                        $nurVerweis = true;
                                        $verweisAuf = $this->tabSchwerpunktKurse[$k][0];
                                        break;
                                    }
                                }

                                if ($nurVerweis) {
                                    break;
                                }

                            }
                        }

                        $this->modulSeiteSchreiben($modTab[$j], $mainSection, $this->custom_styles['tableStyle'], 3, $nurVerweis, $verweisAuf);
                    }

                }
            }
            elseif ($this->inputArray['aufteilung'] == "alle") {
                $mainSection->addTitle("Moduledetails", 1);
                $mainSection->addPageBreak();

                //Module sortiert schreiben
                for ($i = 0; $i < sizeof($this->tabKursSchwerpunkte); $i++) { //Loop über Kurse
                    $this->modulSeiteSchreiben($this->tabKursSchwerpunkte[$i][0], $mainSection, $this->custom_styles['tableStyle'], 2,false, null);
                }
            }
            else {
                //ERROR
            }


            /*
             * Hinweistext zu nicht enthaltenden Modulen
             * =========================================
             */

            $endSection = $phpWord->addSection(); //Hinweis auf letzter Seite des Dokuments
            $endSection->addTitle("Hinweise zu anderen Veranstaltungen",1);
            $endSection->addText("Schwerpunkt Studium Generale", $this->custom_styles['endInfoStyle']);
            $endSection->addText("Im Schwerpunkt -Studium Generale- können je nach Kapazität Angebote anderer Fakultäten gewählt werden. Die Angebote entnehmen Sie bitte aus Stud-IP.");
            $endSection->addText("Fremdsprachenangebot", $this->custom_styles['endInfoStyle']);
            $endSection->addText("Bei den Wahlmodulen Fremdsprachen / Schlüsselkompetenzen können Sie eine Wirtschaftsfremdsprache aus dem Angebot des Sprachenzentrums der Universität Passau wählen. Das Angebot entnehmen Sie bitte aus dessen Website: http://www.sprachenzentrum.uni-passau.de/fremdsprachenausbildung/ffa/ffa-fuer-wirtschaftswissenschaftler/ Sie wählen Sprachkurse gemäß Ihren (durch Einstufungstest oder Zertifikat festgestellten) Vorkenntnissen. Prüfungsmodul ist das vollständig absolvierte Modul der jeweils höchsten erreichten Stufe. In allen Sprachen wählen Sie ab der Aufbaustufe die Fachsprache Wirtschaft. Englisch kann grundsätzlich erst ab der Aufbaustufe gewählt werden. ");
            $endSection->addText("Schlüsselkompetenzen", $this->custom_styles['endInfoStyle']);
            $endSection->addText("Zusätzlich können Sie Veranstaltungen zu Schlüsselkompetenzen aus dem Angebot des Zentrums für Karriere und Kompetenzen wählen. Das Angebot entnehmen Sie bitte aus dessen Website: http://www.uni-passau.de/studium/service-und-beratung/zkk/veranstaltungen/fuer-studierende/");


        }

        elseif ($this->inputArray['auftrag'] === 'ects') { //ECTS-Liste erstellen
            $user = User::findByUsername($this->inputArray['profUsername']);
            $file = 'ECTS-Liste_' . $this->inputArray['semester'] . '_' .
                $this->inputArray['profUsername'];
            $table = $mainSection->addTable(array(
                'borderColor' => '000000',
                'borderSize'  => 4,
                'cellMargin'  => 80
            ));

            $alleKurse = CourseMember::findByUser($user->id);

            if($this->inputArray['semester'] === 'all'){ //alle Semester
                $headerSection->addText("ECTS-Liste für " . $user->getFullName() . " (alle Semester)", $this->custom_styles['headerStyle'], $this->custom_styles['centerStyle']);

                $table->addRow();
                $table->addCell()->addText("Name", $this->custom_styles['titleStyle']);
                $table->addCell()->addText("Semester", $this->custom_styles['titleStyle']);
                $table->addCell()->addText("Typ", $this->custom_styles['titleStyle']);
                $table->addCell()->addText("ECTS", $this->custom_styles['titleStyle']);

                foreach ($alleKurse as $kurs) {
                    $table->addRow();
                    $table->addCell()->addText($this->encodeText($kurs->course_name));
                    $table->addCell()->addText($this->encodeText($kurs->course->start_semester->name));
                    $table->addCell()->addText($this->encodeText($kurs->course->getSemType()['name']));
                    $table->addCell()->addText($this->encodeText($kurs->course->ects));
                }
            }
            else { //ein bestimmtes Semester

                $headerSection->addText("ECTS-Liste für " . $user->getFullName() . " (" . $this->inputArray['semester'] . ")", $this->custom_styles['headerStyle'], $this->custom_styles['centerStyle']);

                $table->addRow();
                $table->addCell()->addText("Name", $this->custom_styles['titleStyle']);
                $table->addCell()->addText("Typ", $this->custom_styles['titleStyle']);
                $table->addCell()->addText("ECTS", $this->custom_styles['titleStyle']);

                foreach ($alleKurse as $kurs) {
                    if ($kurs->course->start_semester->name === $this->inputArray['semester']) {
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
        if ($this->inputArray['datei'] === 'docx'&&$this->inputArray['log'] !== 'on') {//DOCX und kein Log
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
        elseif ($this->inputArray['datei'] === 'docx'&&$this->inputArray['log'] === 'on'){//DOCX und Log
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
        elseif ($this->inputArray['datei'] === 'pdf' && $this->inputArray['log'] !== 'on') {//PDF und kein Log
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
        elseif ($this->inputArray['datei'] === 'pdf' && $this->inputArray['log'] === 'on') {//PDF und Log
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
        //$tmp = nl2br(html_entity_decode(htmlentities($text)));
        //\PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(false);
        //return str_replace("<br />", "TEST123 <w:br/> TEST123", $tmp);

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
    //TODO: INDENT!
    public function addTextToTable($table, $titel, $inhalt){
        $fixLists = true;
        $table->addRow();
        $table->addCell()->addText($titel);
        $tmpCell = $table->addCell();



        $textlines = explode("\n", $this->encodeText($inhalt));
        for ($i = 0; $i < sizeof($textlines); $i++) {
            $textToPrint = $textlines[$i];

            //if(substr($textlines[$i], 0, 1) == '•' || substr($textlines[$i], 0, 1) == '–')
            if($fixLists && preg_match('/[\'•–-]/', mb_substr($textToPrint, 0, 1)))
            {
                if(preg_match('/[\t]/', mb_substr($textToPrint, 1, 1))) {
                    $textToPrint = mb_substr($textToPrint, 2);
                }
                elseif(mb_substr($textToPrint, 1, 2) == '  ') {
                    $textToPrint = mb_substr($textToPrint, 3);
                }
                elseif(mb_substr($textToPrint, 1, 1) == ' ') {
                    $textToPrint = mb_substr($textToPrint, 2);
                }
                else {
                    $textToPrint = mb_substr($textToPrint, 1);
                }
                $tmpCell->addListItem($textToPrint, 0);
            }
            else {
                //check for empty lines in
                $begin_check = 0;
                //$begin_check = sizeof($textlines)-1; //if only last line
                if((empty($textToPrint) || $textToPrint === " " || (strlen($textToPrint)==1 && preg_match('/^([^\d]+)$/', $textToPrint))) && ($i >= $begin_check)) { //no empty lines
                    //skip line
                }
                else {
                    $tmpCell->addText($textToPrint, $this->custom_styles['modTableTabName']);
                }
            }
        }
    }

    /**
     * @param $table Table Tabellenobjekt, in welches geschrieben wird
     * @param $titel String Titel des Eintrags in die Tabelle
     * @param $inhalt String Inhalt des Eintrags in die Tabelle
     * @param $ignoreEmpty Sollen leere Inhalte ignoriert werden?
     */
    public function addTextToTable2($table, $titel, $inhalt, $ignoreEmpty = false, $firstLetterUppercase = true){
        if($inhalt == '' && $ignoreEmpty) {
            #do nothing
        }
        else {
            if($firstLetterUppercase)
                $inhalt = ucfirst($inhalt);

            $this->addTextToTable($table, $titel, $inhalt);
        }
    }

    /**
     * Befüllt die Tabelle in der die Modulübersicht gespeichert ist
     * @param $cours Course Kursobjekt
     * @param $modul String Modulzuordnung
     */
    public function modulUebersicht($cours, $modul){
        $bool = true; //toAdd
        foreach ($this->modulTabelle as $modTab) {
            if(in_array($modul, $modTab))
               $bool = false;
        }
        if($bool){ //add
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
     * Befüllt die Tabelle in der die Modulübersicht gespeichert ist
     * @param $kursobjekt Course Kursobjekt
     * @param $modulzuordnung String Modulzuordnung
     */
    public function modulOrdnung($kursobjekt, $modulzuordnung){
        $bool = true;
        foreach ($this->tabSchwerpunktKurse as $modTab) { //überprüfen, ob Modulzuordnung (also Schwerpunktname) schon im Table ist
            if(in_array($modulzuordnung, $modTab))
                $bool = false;
        }
        if($bool){ // nicht im Table
            array_push($this->tabSchwerpunktKurse, array($modulzuordnung));
        }
        //Modulzuordnung (also Schwerpunktname) ist erstes Element im Array $this->modulOrdnungsTabelle[0]

        for ($i = 0; $i<sizeof($this->tabSchwerpunktKurse); $i++){
            for($j = 0; $j<sizeof($this->tabSchwerpunktKurse[$i]); $j++){
                if($this->tabSchwerpunktKurse[$i][$j] === $modulzuordnung){
                    array_push($this->tabSchwerpunktKurse[$i], $kursobjekt);
                }
            }
        }
    }

    /**
     * Befüllt die Tabelle in der die Kurse mit ihren zugehörigen Schwerpubkten gespeichert sind
     * @param $kursobjekt Course Kursobjekt
     * @param $modulzuordnung String Modulzuordnung
     */
    public function modulOrdnungSimple($kursobjekt, $modulzuordnung){
        $bool = true; //isNew

        for ($i = 0; $i<sizeof($this->tabKursSchwerpunkte); $i++){
            if($this->tabKursSchwerpunkte[$i][0] == $kursobjekt) {
//                for($j = 1; $j<sizeof($this->modulOrdnungsTabelleSimple[$i]); $j++){
//
//                }
                //Kurs ist bereits in Array -> Nur Schwerpunkt hinzufügen.
                $bool = false;
                array_push($this->tabKursSchwerpunkte[$i], $modulzuordnung);

                break; //increase speed
            }
        }

        //Neuen Kurs mit Schwerpunkt hinzufügen
        if($bool) {
            $nextFreeIndex = sizeof($this->tabKursSchwerpunkte);
            $this->tabKursSchwerpunkte[$nextFreeIndex] = array($kursobjekt, $modulzuordnung);
        }
    }

    /**
     * @param $moduleCostomOrder Ordered array of names of Schwerpunkte
     */
    public function modulzuordnungUndKurseOrdnen($moduleCostomOrder, $sorttype, $removeEmpty = true){

        //modulOrdnungsTabelle Sortieren
                $tmpModulzuordnungenSource=$this->tabSchwerpunktKurse;
                $tmpModulzuordnungenTarget=array();

                $currentFreePos=0;

                // Konfigurierte Schwerpunktreihenfolge einhalten
                foreach ($moduleCostomOrder as $currentModulePos) {
                    for($i = 0; $i<sizeof($tmpModulzuordnungenSource); $i++){
                        if($tmpModulzuordnungenSource[$i][0] == $currentModulePos) {
                            $tmpModulzuordnungenTarget[$currentFreePos] = $tmpModulzuordnungenSource[$i];
                            $currentFreePos = $currentFreePos + 1;
                            unset($tmpModulzuordnungenSource[$i]); //remove currently re-added module
                            break;
                        }
                    }
                }

                //sort rest
                usort($tmpModulzuordnungenSource, function($a, $b) {
                    return strcmp($a[0], $b[0]);
                });

                //add rest
                foreach ($tmpModulzuordnungenSource as $currentLeftModule) {
                    $tmpModulzuordnungenTarget[$currentFreePos] =  $currentLeftModule;
                    $currentFreePos = $currentFreePos + 1;
                }

                //leere Schwerpunkte entfernen
                if($removeEmpty) {
                    for($i = 0; $i<sizeof($tmpModulzuordnungenTarget); $i++){
                        if(sizeof($tmpModulzuordnungenTarget[$i]) <= 1) { //only title itself is countained!
                            unset($tmpModulzuordnungenTarget[$i]); //remove Schwerpunkt.
                        }
                    }
                }

                // Kurse der jeweiligen Schwerpunkte sortieren

                for($i = 0; $i<sizeof($tmpModulzuordnungenTarget); $i++){
                    //foreach ($tmpModulzuordnungenTarget as $modulKurse) {
                    $modulKurse = $tmpModulzuordnungenTarget[$i];
                    $tmp = $modulKurse[0];
                    unset($modulKurse[0]);

                    usort($modulKurse, function($a, $b) use ($sorttype) {
                        if($sorttype == 'name') {
                            return strcmp($a->name, $b->name);
                        }
                        elseif($sorttype == 'num') {
                            $a_num = preg_replace("/[^0-9.]/", "", $a->veranstaltungsnummer);
                            $b_num = preg_replace("/[^0-9.]/", "", $b->veranstaltungsnummer);

                            if ($a_num == $b_num) {
                                return 0;
                            }
                            else {
                                return ($a_num < $b_num) ? -1 : 1;
                            }
                        }
                    });

                    $modulKurseFertig = array($tmp);
                    $modulKurseFertig = array_merge($modulKurseFertig, $modulKurse);
                    $tmpModulzuordnungenTarget[$i] = $modulKurseFertig;
                    //$kursobjekt->veranstaltungsnummer."\t".$kursobjekt->name
                }

                $this->tabSchwerpunktKurse =  $tmpModulzuordnungenTarget;


        //modulOrdnungsTabelleSimple Sortieren
                $tmpModulzuordnungenSimpleSource=$this->tabKursSchwerpunkte;
                $tmpModulzuordnungenSimpleTarget=array();


                //Kurse sortieren
                usort($tmpModulzuordnungenSimpleSource, function($a, $b) use ($sorttype) {
                    if($sorttype == 'name') {
                        return strcmp($a[0]->name, $b[0]->name);
                    }
                    elseif($sorttype == 'num') {
                        $a_num = preg_replace("/[^0-9.]/", "", $a[0]->veranstaltungsnummer);
                        $b_num = preg_replace("/[^0-9.]/", "", $b[0]->veranstaltungsnummer);

                        if ($a_num == $b_num) {
                            return 0;
                        }
                        else {
                            return ($a_num < $b_num) ? -1 : 1;
                        }
                    }
                });

                //Schwerpunkte der Kurse sortieren
                for($i = 0; $i<sizeof($tmpModulzuordnungenSimpleSource); $i++) {
                    if(sizeof($current_schwerpunkte_source) > 1) {
                        $current_kurs = $tmpModulzuordnungenSimpleSource[$i][0];
                        $current_schwerpunkte_source = array_slice($tmpModulzuordnungenSimpleSource[$i], 1);
                        $current_schwerpunkte_target = array();

                        // Konfigurierte Schwerpunktreihenfolge einhalten
                        foreach ($moduleCostomOrder as $currentModulePos) {
                            for ($j = 0; $j < sizeof($current_schwerpunkte_source); $j++) {
                                if ($current_schwerpunkte_source[$j] == $currentModulePos) {
                                    $currentFreePos = sizeof($current_schwerpunkte_target);
                                    $current_schwerpunkte_target[$currentFreePos] = $current_schwerpunkte_source[$j];
                                    unset($current_schwerpunkte_source[$j]); //remove currently re-added schwerpunkt
                                    break;
                                }
                            }


                        }


                        //sortiere den Rest
                        usort($current_schwerpunkte_source, function($a, $b) {
                            return strcmp($a, $b);
                        });


                            //füge alles zusammen
                            $current_schwerpunkte_target = array_merge($current_schwerpunkte_target, $current_schwerpunkte_source);

                            $tmpModulzuordnungenSimpleTarget[$i] = array($current_kurs);
                            $tmpModulzuordnungenSimpleTarget[$i] = array_merge($tmpModulzuordnungenSimpleTarget[$i], $current_schwerpunkte_target);
                    }
                    else {
                        //nur kopieren
                        $tmpModulzuordnungenSimpleTarget[$i] = $tmpModulzuordnungenSimpleSource[$i];
                    }
                }


               $this->tabKursSchwerpunkte =  $tmpModulzuordnungenSimpleTarget;
    }

    public function sortViaName($a, $b){

    }

    public function sortViaFirst($a, $b) {
        return strcmp($a[0], $b[0]);
    }

    public function modulEinordnen($kursobjekt, $modulzuordnung){
        $this->modulUebersicht($kursobjekt, $modulzuordnung);
        $this->modulOrdnung($kursobjekt, $modulzuordnung);
        $this->modulOrdnungSimple($kursobjekt,$modulzuordnung);
    }



    public function modulSeiteSchreiben($kursobjekt, $section, $tabStyle, $headindDepth, $nurVerweis = false, $verweisAuf){
        $addVeranstaltungsnummer = true;
        $removeBAMA = true;
        $addPN = false;

        $nameToPrint = "";

        if($addVeranstaltungsnummer)
            $nameToPrint = $nameToPrint.$kursobjekt->veranstaltungsnummer."\t";

        $kursname = $kursobjekt->name;

        if($removeBAMA)
            $kursname = str_replace(" (Bachelor)", "", str_replace(" (Master)", "", $kursname));

        $nameToPrint = $nameToPrint.$kursname;

        if($addPN)
            $nameToPrint = $nameToPrint." (PN: ".$this->getPN($kursobjekt).")";

        if($nurVerweis) {
            $nameToPrint = $nameToPrint." (siehe ".$verweisAuf.")";
        }

        $section->addTitle($this->encodeText($nameToPrint), $headindDepth);

        if($nurVerweis) {
            $section->addText("Bitte entnehmen Sie die Veranstaltungsdetails aus der unter \"".$verweisAuf."\" gezeigten Übersicht.");
        }
        else {
            $table = $section->addTable($tabStyle);
            $this->addTableToDoc($kursobjekt, $table);
        }

        $section->addPageBreak();
    }

    public function modulGruppeSchreiben($modulzuordnung, $section) {
        $section->addTitle($this->encodeText($modulzuordnung),2);
        $section->addPageBreak();
    }




    public function moduleSortiertDrucken(){

    }

    public function getPN($cours) {
        $studyareastring = $cours->study_areas->first()->name;
        $result = substr($studyareastring, 0, strpos($studyareastring, " | "));

        return $result;
    }

    public function getSchwerpunkt($kursobjekt) {
        $result = "";
        for($i = 0; sizeof($this->tabKursSchwerpunkte); $i++) {
            if($this->tabKursSchwerpunkte[$i][0] == $kursobjekt) {
                $result = implode("hallo1234 \n", array_slice($this->tabKursSchwerpunkte[$i], 1));

                break; //increase speed
            }
        }

        return $result;
    }

    //https://akrabat.com/substr_in_array/
    public function in_array_substr($item, array $hintArray, $lowercase=true)
    {
        return ($this->in_array_strpos($item, $hintArray, $lowercase) != -1);
    }

    public function in_array_strpos($item, array $hintArray, $lowercase=true)
    {
        if($lowercase) {
            $item = strtolower($item);
        }

        foreach ($hintArray as $hint) {
            if($lowercase) {
                $hint = strtolower($hint);
            }

            $pos = strpos($item, $hint);
            if (false !== $pos) {
                return $pos;
            }
        }
        return -1;
    }

    public function in_array_strposZero($item, array $hintArray, $lowercase=true)
    {
        if($lowercase) {
            $item = strtolower($item);
        }

        foreach ($hintArray as $hint) {
            if($lowercase) {
                $hint = strtolower($hint);
            }

            $pos = strpos($item, $hint);
            if (false !== $pos && $pos == 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Schreibt Kursdetails in die angehängte Tabelle
     * @param $cours Course Kursobjekt
     * @param $table Table Tabellenobjekt, in welches geschrieben wird
     * @param $modul String Modulzuordnung
     */
    public function addTableToDoc($cours, $table)
    {
        $tmp_debug ="";
        $tabLang = "de";
        $englishHints = array("(ENGLISCH)", "Course language is English.");
        $tmp_dur = 0;
        $tmp_start = "";
        $tmp_end = "";
        $tmp_laenge = "";

        //get additional data
        foreach ($cours->datafields as $datafield) {
            if ($datafield->name === "SWS") {
                $tmp_sws = $datafield->content;
            }
            elseif ($datafield->name === "Turnus") {
                $tmp_turnus = $datafield->content;
            }
            elseif ($datafield->name === "Literatur") {
                $tmp_literatur = $datafield->content;
            }
            elseif ($datafield->name === "Qualifikationsziele") {
                $tmp_qualifikationsziele = $datafield->content;
            }
            elseif ($datafield->name === "Workload") {
                $tmp_workload = $datafield->content;
            }
            elseif ($datafield->name === "Hinweise zur Anrechenbarkeit") {
                $tmp_anrechenbarkeit = $datafield->content;
            }
            else{
                $tmp_debug = $tmp_debug."'".$datafield->name."'='".$datafield->content."';  ";
            }
        }

        $tmp_pn = $this->getPN($cours);

        $tmp_schwerpunkt = $this->getSchwerpunkt($cours);

        //preprocess data
        if($this->inputArray['sprachenkonvertierung'] == 'on') {
            if ($this->in_array_substr($cours->untertitel, $englishHints) || $this->in_array_substr($cours->sonstiges, $englishHints)) {
                $tabLang = "en";
                //setlocale (LC_ALL, 'en_GB');
                setTempLanguage(false, "en_GB");
                //setLocaleEnv("en_GB");
                $tmp_debug = $tmp_debug . " LANG: en/" . getUserLanguage(get_userid()) . "/" . get_accepted_languages();
            }
        }
        $tabTexts = array(
            "de" => array(
                'unt' => 'Untertitel',
                'vnr' => 'Veranstaltungsnummer',
                'typ' => 'Typ der Veranstaltung',
                'mod' => 'Moduleinordnung',
                'doz' => 'Dozenten',
                'ein' => 'Heimateinrichtung',
                'art' => 'Art/Form',
                'inh' => 'Inhalt des Moduls / Beschreibung',
                'qua' => 'Qualifikationsziele des Moduls',
                'met' => 'Lehr- und Lernmethoden des Moduls',
                'vor' => 'Voraussetzungen für die Teilnahme',
                'hae' => 'Häufigkeit des Angebots des Moduls',
                'lae' => 'Länge des Moduls',
                'wor' => 'Workload des Moduls',
                'ect' => 'ECTS',
                'pnr' => 'Prüfungsnummer',
                'pru' => 'Art der Prüfung/Voraussetzung für die Vergabe von Leistungspunkten/Dauer der Prüfung',
                'lit' => 'Empfohlene Literaturliste (Lehr- und Lernmaterialien, Literatur)',
                'anr' => 'Hinweise zur Anrechenbarkeit',
                'son' => 'Sonstiges / Besonderes (z.B. Online-Anteil, Praxisbesuche, Gastvorträge, etc.)',
                'tei' => 'Teilnehmer',
                'deb' => 'DEBUG:'
            ),
            "en" => array(
                'unt' => 'Subtitle',
                'vnr' => 'Course Number',
                'typ' => 'Courses Type',
                'mod' => 'Course Allocation',
                'doz' => 'Lecturer',
                'ein' => 'Home Institute',
                'art' => 'Type/Form',
                'inh' => 'Content/Description',
                'qua' => 'Qualification Goals',
                'met' => 'Learning organization',
                'vor' => 'Pre-Requisites',
                'hae' => 'Rotation',
                'lae' => 'Length',
                'wor' => 'Workload',
                'ect' => 'ECTS',
                'pnr' => 'Test Number',
                'pru' => 'Performance Record',
                'lit' => 'Recommended Literature (Teaching and Learning Materials, Literature)',
                'anr' => 'Notes on Creditability',
                'son' => 'Miscellaneous',
                'tei' => 'Current Number of Participants',
                'deb' => 'DEBUG:'
            ),
        );

        //        if($tmp_start != '' && $tmp_end != ''){
        //            $tmp_dur = $tmp_start." - ".$tmp_end." => ". round((strtotime($tmp_end) - strtotime($tmp_start)) / (60 * 60 * 24)); //date_diff(strtotime($tmp_end) - strtotime($tmp_start));
        //
        //            if($tmp_dur > 100)
        //                $tmp_laenge = "2 semestrig";
        //            else {
        //                $tmp_laenge = "1 semestrig";
        //            }
        //        }


        //ORDER MATTERS (SPEED!)
        $turnusNamen = array("Jährlich, jeweils im Wintersemester", "Jährlich, jeweils im Sommersemester",
                             "jeweils im Wintersemester", "jeweils im Sommersemester",
                             "jeweils im Wintersemster", "jeweils im Sommersemster",
                             "jedes Semester",
                             "jedes Semster",
                             "jedes Sommersemester, 1 Semester", "jedes Wintersemester, 1 Semester",
                             "jedes Sommersemester", "jedes Wintersemester",
                             "Jedes Sommer- und Wintersemester", "Jedes Winter- und Sommersemester",
                             "every summer semester", "every winter semester",
                             "Bitte beachten Sie die Hinweise auf der Lehrstuhl-Homepage",
                             "Sommersemester", "Wintersemester");
        if($tmp_turnus != "" && !$this->in_array_strposZero($tmp_turnus, $turnusNamen)) {
                $tmp_turnus = $tmp_turnus."\nBitte entnehmen Sie gegebenenfalls die konkrete Dauer aus den weiteren Angaben.";
        }

        //sortierter Inhalt
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['unt'].$textSuf, $cours->untertitel,                                                       true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['vnr'].$textSuf, $cours->veranstaltungsnummer,                                             true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['typ'].$textSuf, $cours->getSemType()['name'],                                             true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['mod'].$textSuf, $tmp_schwerpunkt,                                                         true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['doz'].$textSuf, $this->dozenten($cours),                                                  true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['ein'].$textSuf, Institute::find($cours->institut_id)->name,                               true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['art'].$textSuf, $cours->art,                                                              true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['inh'].$textSuf, $cours->beschreibung,                                                     true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['qua'].$textSuf, $tmp_qualifikationsziele,                                                 true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['met'].$textSuf, $cours->lernorga,                                                         true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['vor'].$textSuf, $cours->vorrausetzungen,                                                  true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['hae'].$textSuf, $tmp_turnus,                                                              true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['lae'].$textSuf, $tmp_dur,                                                                 true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['wor'].$textSuf, $tmp_workload,                                                            true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['ect'].$textSuf, $cours->ects,                                                             true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['pnr'].$textSuf, $tmp_pn,                                                                  true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['pru'].$textSuf, $cours->leistungsnachweis,                                                true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['lit'].$textSuf, $tmp_literatur,                                                           true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['anr'].$textSuf, $tmp_anrechenbarkeit,                                                     true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['son'].$textSuf, $cours->sonstiges,                                                        true);
        //$this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['tei'].$textSuf, $cours->teilnehmer,                                                       true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['deb'].$textSuf, $tmp_debug,                                                               true);


        restoreLanguage();


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

