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
    private $modulOrdnungsTabelle;

    /**
     * Aktionen und Einstellungen, werden vor jedem Seitenaufruf aufgerufen
     */
    public function before_filter(&$action, &$args){

        $this->plugin = $this->dispatcher->plugin;
        $this->flash = Trails_Flash::instance();

        $this->set_layout(Request::isXhr() ? null : $GLOBALS['template_factory']->open('layouts/base'));

        $this->modulTabelle = array();
        $this->modulOrdnungsTabelle = array();
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
                "fullyear" => Request::get("modul_fullyear"),
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

        //TODO:omPDF wird zur Erzeugung von PDFs verwendet -> für spätere Versionen
        //TODO: Alternativ -> https://stackoverflow.com/questions/33084148/generate-pdf-from-docx-generated-by-phpword
        $options = new Options();
        $options->setChroot($GLOBALS['TMP_PATH']);
        $dompdf = new Dompdf($options);

        //Sections
        $headerSection = $phpWord->addSection(); //Titel des Dokuments ALT: und Gliederung
        $headerStyle = array('name' => 'Tahoma', 'size' => 16, 'bold' => true);

        $tocSection = $phpWord->addSection(); //Inhaltsverzeichnisse
        $tocFooter = $tocSection->addFooter();
        $tocFooter->addPreserveText('Seite {PAGE} von {NUMPAGES}', null, array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));
        $fontStyle12 = array('spaceAfter' => 60, 'size' => 12);
        $fontStyle11 = array('spaceAfter' => 60, 'size' => 11);

        $preContentSection = $phpWord->addSection(); //Zuordnungen

        $mainSection = $phpWord->addSection(array('breakType' => 'continuous')); //Inhalt des Dokuments
        $mainFooter = $mainSection->addFooter();
        $mainFooter->addPreserveText('Seite {PAGE} von {NUMPAGES}', null, array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));

        //Styles
        $styleFrontMatterHeading = array('name' => 'Arial', 'size' => 28, 'bold' => true,  'alignment' => Jc::CENTER);
        $styleFrontMatterText    = array('name' => 'Arial', 'size' => 15, 'bold' => false, 'alignment' => Jc::CENTER);
        $titleStyle = array('name' => 'Arial', 'size' => 12, 'bold' => true);
        $centerStyle = array('alignment' => Jc::CENTER);
        $leftStyle = array('alignment' => Jc::LEFT);
        $tableStyle = array('cellMargin' => 40, 'borderSize' => 1);
        $endInfoStyle = array('size' => 12, 'underline' => Font::UNDERLINE_SINGLE);





        //
        $phpWord->addParagraphStyle('modTableTab', array('tabs' => array(new \PhpOffice\PhpWord\Style\Tab('left', 7000))));
        $phpWord->addParagraphStyle('P-listStyle', array('hanging'=>0, 'left'=>0, 'lineHeight'=>1, 'color'=>'FFFAE3'));
        $phpWord->addTitleStyle(0, $titleStyle);
        $phpWord->addTitleStyle(1, $headerStyle, $centerStyle);
        $phpWord->addTitleStyle(2, $headerStyle, $centerStyle);
        $phpWord->addTitleStyle(3, $headerStyle, $centerStyle);


        if ($inputArray['auftrag'] === 'modul') { //Modulkatalog erstellen
            /*
             * SETTINGS
             * ========
             */

            //Ordnung der Schwerpunkte
                $moduleCostomOrder=array("Basismodule", "Wahlpflichtmodule");



            /*
             * FRONT MATTER
             */

                /*
                 * PREPROCESSING
                 */


                    $courseName = $inputArray['studiengang'];
                    $courseNameSub = "";
                    $nameBAMA=array("Bachelor", "Master");
                    $courseLevel=""; //Bachelor oder Master
                    $frontMatterCourseFound=0;

                    foreach($nameBAMA as $name) {
                        if(strpos($courseName, $name) !== false) {
                            $frontMatterCourseFound = 1;
                            $courseLevel = $name;
                            $courseName = str_replace($name." ", "", $courseName);

                            //Sonderfälle
                            if($name == $nameBAMA[0] && $courseName == 'Wirtschaftsinformatik' && $inputArray['po'] == 'Version WS 2015'){
                                $courseNameSub = "(Information Systems)";
                            }

                            break; //increase speed
                        }
                    }

                    $semesterName = $inputArray['semester'];
                    $semesterName = str_replace("WiSe", "WS", $semesterName);
                    $semesterName = str_replace("SoSe", "SS", $semesterName);


                    $applicableSemesters = array($inputArray['semester']);
                    if($inputArray['fullyear'] === 'on') {
                        $semesterPrev = "";
                        if(substr($inputArray['semester'],0,4) == "WiSe") {
                            $tmpNum = substr($inputArray['semester'],5,2);
                            $semesterPrev = "SoSe ".$tmpNum;
                        }
                        elseif(substr($inputArray['semester'],0,4) == "WiSe") {
                            $tmpNum = intval(substr($inputArray['semester'],5,2))+1;
                            if(strlen($tmpNum)==1)
                            {
                                $tmpNum = "0".$tmpNum;
                            }
                            $semesterPrev = "WiSe ".$tmpNum;
                        }

                        array_push($applicableSemesters, $semesterPrev);
                    }



                /*
                 * OUTPUT
                 */

                    //$headerSection->addTitle("Modulkatalog für " . $inputArray['studiengang'] .
                    //    " (" . $inputArray['po'] . ")" . " im " . $inputArray['semester'],0);
                    //$headerSection->addText("Enthaltene Module:", array('size' => 14, 'underline' => Font::UNDERLINE_SINGLE));
                    //$headerSection->addText("\t".$inputArray['studiengang'], 'modTableTab');

                    $headerSection->addImage(__DIR__.'/../src/logo01.png', array('width' => 300, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));

                    if($frontMatterCourseFound == 1) {
                        $headerSection->addText($courseLevel."-Studiengang", $styleFrontMatterHeading);
                        $headerSection->addText("", $styleFrontMatterHeading);
                    }

                    $headerSection->addText($courseName, $styleFrontMatterHeading);

                    if($courseNameSub !== "") {
                        $headerSection->addText($courseNameSub, $styleFrontMatterHeading);
                    }
                    else {
                        $headerSection->addText("", $styleFrontMatterHeading);
                    }
                    $headerSection->addText("", $styleFrontMatterHeading);

                    $headerSection->addText("Modulkatalog", $styleFrontMatterHeading);
                    $headerSection->addText("", $styleFrontMatterHeading);

                    $headerSection->addText($semesterName, $styleFrontMatterHeading);

                    $headerSection->addText("", $styleFrontMatterText);
                    $headerSection->addText("Primäre Prüfungsordnung: ".$inputArray['po'], $styleFrontMatterText);
                    $headerSection->addText("Stand: ".date('d. F Y'), $styleFrontMatterText);
                    $headerSection->addText("", $styleFrontMatterText);
                    $headerSection->addText("", $styleFrontMatterText);

                    $headerSection->addText("Falls Sie ältere Versionen des Modulkatalogs benötigen, setzen Sie sich bitte mit dem Dekanat der Wirtschaftswissenschaftlichen Fakultät in Verbindung (dekanat.wiwi@uni-passau.de).", $styleFrontMatterText);
                    $headerSection->addText("", $styleFrontMatterText);
                    $headerSection->addText("Für alle aufgeführten Veranstaltungen des Modulkatalogs gelten die Studien- und Qualifikationsvoraussetzungen gemäß der jeweiligen Prüfungs- und Studienordnung.", $styleFrontMatterText);



            $tocSection->addTitle('Inhaltsverzeichnis');
            //$tmpStyle123 = array('indentation' => array('left' => 540, 'right' => 120), 'bold' => true);
            //$tocSection->setStyle($tmpStyle123);
            $toc = $tocSection->addTOC($fontStyle11, array('indent' => 100));

            $count = 1;


            $file = 'Modulkatalog_';
            foreach($applicableSemesters as $applicableSemester) {
                $file = $file.$applicableSemester."_";
            }
            $file = $file.$inputArray['studiengang'];

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
                                    if (in_array($cours->start_semester->name, $applicableSemesters) &&
                                        //$cours->start_semester->name === $inputArray['semester'] && //nach ausgewähltem Semester filtern
                                        in_array($cours->getSemType()['name'], $relevanteVaTypen) && //nach Vorlesungen und Seminaren filtern

                                        $f->name !== "Studium Generale"){ //Studium Generale nicht anzeigen
                                        //$headerSection->addText($count .". ".$this->encodeText($cours->name));
                                        //$mainSection->addTitle($count++ .". ".$this->encodeText($cours->name),2);
                                        //$table = $mainSection->addTable($tableStyle);
                                        //$this->addTableToDoc($cours, $table, $f->name);
                                        //$this->modulUebersicht($cours, $f->name);
                                        $this->modulEinordnen($cours, $f->name);

                                        //$mainSection->addPageBreak();


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
                                    if (in_array($cours->start_semester->name, $applicableSemesters) &&
                                        //$cours->start_semester->name === $inputArray['semester'] && //nach ausgewähltem Semester filtern
                                        in_array($cours->getSemType()['name'], $relevanteVaTypen)){ //nach Vorlesungen und Seminaren filtern
                                        //$headerSection->addText($count .". ".$this->encodeText($cours->name));
                                        //$mainSection->addTitle($count++ .". ".$this->encodeText($cours->name), 2);
                                        //$table = $mainSection->addTable($tableStyle);
                                        $modName = $m->name . " - " . $f->name;
                                        //$this->addTableToDoc($cours, $table, $modName);
                                        //$this->modulUebersicht($cours, $modName);
                                        $this->modulEinordnen($cours, $modName);
                                        //$mainSection->addPageBreak();
                                    }
                                }
                            }

                        }

                    }
                    else { //Rest - keine Abstufung
                        foreach ($faecher as $f) {
                            $courses = $f->courses;
                            foreach ($courses as $cours) {
                                if (in_array($cours->start_semester->name, $applicableSemesters) &&
                                    //$cours->start_semester->name === $inputArray['semester'] && //nach ausgewähltem Semester filtern
                                    in_array($cours->getSemType()['name'], $relevanteVaTypen)){ //nach Vorlesungen und Seminaren filtern
                                    //$headerSection->addText($count .". ".$this->encodeText($cours->name));
                                    //$mainSection->addTitle($count++ .". ".$this->encodeText($cours->name), 2);
                                    //$table = $mainSection->addTable($tableStyle);
                                    //$this->addTableToDoc($cours, $table, $m->name);
                                    //$this->modulUebersicht($cours, $m->name);
                                    $this->modulEinordnen($cours, $m->name);
                                    //$mainSection->addPageBreak();
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

            $this->modulzuordnungUndKurseOrdnen($moduleCostomOrder);


            foreach ($this->modulOrdnungsTabelle as $modTab) {

                $currentSection->addText($modTab[0], $titleStyle, $leftStyle);

                $modTable = $currentSection->addTable(array(
                    'borderColor' => '000000',
                    'borderSize' => 4,
                    'cellMargin' => 40
                ));

                for ($j = 1; $j < sizeof($modTab); $j++) {
                    $modTable->addRow();
                    $cell = $modTable->addCell();
                    $cours = $modTab[$j];

                    $cell->addText($this->encodeText($cours->veranstaltungsnummer."\t".$cours->name));
                    //$cell->addListItem($this->encodeText($modTab[$j]), ListItem::TYPE_BULLET_FILLED);
                }

                $tocSection->addTextBreak();
            }
            $tocSection->addPageBreak(); //erstellt Seitenumbruch nach der Modulzuordnung


            $mainSection->addTitle("Module nach Zuordnung",1);
            $mainSection->addPageBreak();

            //Module sortiert schreiben
            $schonGezeigtStattAusgabe = true;
            for($i = 0; $i<sizeof($this->modulOrdnungsTabelle); $i++) {//$this->modulOrdnungsTabelle as $modTab) { //Loop über Schwerpunkte
                $modTab = $this->modulOrdnungsTabelle[$i];

                //Schwerpunktseite schreiben
                $this->modulGruppeSchreiben($modTab[0], $mainSection);

                //Enthaltene Veranstaltungen schreiben
                for ($j = 1; $j < sizeof($modTab); $j++) { //Loop über Veranstaltungen
                    $nurVerweis = false;
                    $verweisAuf="";

                    if($schonGezeigtStattAusgabe) {
                        for($k = 0; $k < $i; $k++)
                        {
                            for($l = 1; $l<sizeof($this->modulOrdnungsTabelle[$k]); $l++) {
                                if($this->modulOrdnungsTabelle[$k][$l]->veranstaltungsnummer == $modTab[$j]->veranstaltungsnummer){
                                    $nurVerweis = true;
                                    $verweisAuf=$this->modulOrdnungsTabelle[$k][0];
                                    break;
                                }
                            }

                            if($nurVerweis){
                                break;
                            }

                        }
                    }

                    $this->modulSeiteSchreiben($modTab[$j], $modTab[0], $mainSection, $tableStyle, $nurVerweis, $verweisAuf);
                }

            }


            /*
             * Hinweistext zu nicht enthaltenden Modulen
             * =========================================
             */

            $endSection = $phpWord->addSection(); //Hinweis auf letzter Seite des Dokuments
            $endSection->addTitle("Hinweise zu anderen Veranstaltungen",1);
            $endSection->addText("Schwerpunkt Studium Generale", $endInfoStyle);
            $endSection->addText("Im Schwerpunkt -Studium Generale- können je nach Kapazität Angebote anderer Fakultäten gewählt werden. Die Angebote entnehmen Sie bitte aus Stud-IP.");
            $endSection->addText("Fremdsprachenangebot", $endInfoStyle);
            $endSection->addText("Bei den Wahlmodulen Fremdsprachen / Schlüsselkompetenzen können Sie eine Wirtschaftsfremdsprache aus dem Angebot des Sprachenzentrums der Universität Passau wählen. Das Angebot entnehmen Sie bitte aus dessen Website: http://www.sprachenzentrum.uni-passau.de/fremdsprachenausbildung/ffa/ffa-fuer-wirtschaftswissenschaftler/ Sie wählen Sprachkurse gemäß Ihren (durch Einstufungstest oder Zertifikat festgestellten) Vorkenntnissen. Prüfungsmodul ist das vollständig absolvierte Modul der jeweils höchsten erreichten Stufe. In allen Sprachen wählen Sie ab der Aufbaustufe die Fachsprache Wirtschaft. Englisch kann grundsätzlich erst ab der Aufbaustufe gewählt werden. ");
            $endSection->addText("Schlüsselkompetenzen", $endInfoStyle);
            $endSection->addText("Zusätzlich können Sie Veranstaltungen zu Schlüsselkompetenzen aus dem Angebot des Zentrums für Karriere und Kompetenzen wählen. Das Angebot entnehmen Sie bitte aus dessen Website: http://www.uni-passau.de/studium/service-und-beratung/zkk/veranstaltungen/fuer-studierende/");


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
                    $tmpCell->addText($textToPrint, 'modTableTab');
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
        foreach ($this->modulOrdnungsTabelle as $modTab) { //überprüfen, ob Modulzuordnung (also Schwerpunktname) schon im Table ist
            if(in_array($modulzuordnung, $modTab))
                $bool = false;
        }
        if($bool){ // nicht im Table
            array_push($this->modulOrdnungsTabelle, array($modulzuordnung));
        }
        //Modulzuordnung (also Schwerpunktname) ist erstes Element im Array $this->modulOrdnungsTabelle[0]

        for ($i = 0; $i<sizeof($this->modulOrdnungsTabelle); $i++){
            for($j = 0; $j<sizeof($this->modulOrdnungsTabelle[$i]); $j++){
                if($this->modulOrdnungsTabelle[$i][$j] === $modulzuordnung){
                    array_push($this->modulOrdnungsTabelle[$i], $kursobjekt);
                }
            }
        }
    }

    /**
     * @param $moduleCostomOrder Ordered array of names of Schwerpunkte
     */
    public function modulzuordnungUndKurseOrdnen($moduleCostomOrder, $removeEmpty = true){
        //echo "test";
        $tmpModulzuordnungenSource=$this->modulOrdnungsTabelle;
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
        $sorttype = "name";
        //$sorttype = "num";

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

        $this->modulOrdnungsTabelle =  $tmpModulzuordnungenTarget;
    }

    public function sortViaName($a, $b){

    }

    public function sortViaFirst($a, $b) {
        return strcmp($a[0], $b[0]);
    }

    public function modulEinordnen($kursobjekt, $modulzuordnung){
        $this->modulUebersicht($kursobjekt,$modulzuordnung);
        $this->modulOrdnung($kursobjekt,$modulzuordnung);
    }



    public function modulSeiteSchreiben($kursobjekt, $modulzuordnung, $section, $tabStyle, $nurVerweis = false, $verweisAuf){
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

        $section->addTitle($this->encodeText($nameToPrint),3);

        if($nurVerweis) {
            $section->addText("Bitte entnehmen Sie die Veranstaltungsdetails aus der unter \"".$verweisAuf."\" gezeigten Übersicht.");
        }
        else {
            $table = $section->addTable($tabStyle);
            $this->addTableToDoc($kursobjekt, $table, $modulzuordnung);
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

    //https://akrabat.com/substr_in_array/
    public function in_array_substr($item, array $hintArray)
    {
        foreach ($hintArray as $hint) {
            if (false !== strpos($item, $hint)) {
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
    public function addTableToDoc($cours, $table, $modul)
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

        //preprocess data
        if($this->in_array_substr($cours->untertitel, $englishHints) || $this->in_array_substr($cours->sonstiges, $englishHints)) {
            $tabLang = "en";
            //setlocale (LC_ALL, 'en_GB');
            setTempLanguage(false,"en_GB");
            //setLocaleEnv("en_GB");
            $tmp_debug = $tmp_debug." LANG: en/".getUserLanguage(get_userid())."/".get_accepted_languages();
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


        $turnusNamen = array("jeweils im Wintersemster", "Jeweils im Wintersemster", "jeweils im Sommersemester", "Jeweils im Sommersemester",
                             "jedes Sommersemester, 1 Semester", "Jedes Sommersemester, 1 Semester", "jedes Wintersemester, 1 Semester", "Jedes Wintersemester, 1 Semester");
        if($tmp_turnus != "" && !in_array($tmp_turnus, $turnusNamen)) {
                $tmp_turnus = $tmp_turnus."\nBitte entnehmen Sie gegebenenfalls die konkrete Dauer aus den weiteren Angaben.";
        }

        //sortierter Inhalt
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['unt'].$textSuf, $cours->untertitel,                                                       true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['vnr'].$textSuf, $cours->veranstaltungsnummer,                                             true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['typ'].$textSuf, $cours->getSemType()['name'],                                             true);
        $this->addTextToTable2($table, $textPre.$tabTexts[$tabLang]['mod'].$textSuf, $modul,                                                                   true);
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

