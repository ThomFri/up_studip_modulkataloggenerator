<?php

class DekanatController extends AuthenticatedController {

    private $BAE_INDEX = 0;
    private $ABWL_INDEX = 0;

    /**
     * Aktionen und Einstellungen, werden vor jedem Seitenaufruf aufgerufen
     */
    public function before_filter(&$action, &$args)
    {
        $this->plugin = $this->dispatcher->current_plugin;
        $this->flash = Trails_Flash::instance();

        $this->set_layout(Request::isXhr() ? null : $GLOBALS['template_factory']->open('layouts/base'));

        $this->sidebar = Sidebar::Get();
        $this->sidebar->setImage('sidebar/file-sidebar.png');

        PageLayout::addScript($this->plugin->getPluginURL().'/views/dekanat/onchange_profs.js');
        PageLayout::addScript($this->plugin->getPluginURL().'/views/dekanat/onchange_regulation.js');

    }

    /**
     * Wird aufgerufen, wenn man sich im 'Modulkatalog erzeugen'-Menüpunkt befindet
     * Variablen für die Views werden festgelegt
     */
    public function modul_action(){
        Navigation::activateItem('/tools/modulkatalog/modul');

        $this->semesters = Semester::getAll();
        $this->current_semester = Semester::findCurrent();

        //TODO: institutes werden nicht richtig angezeigt, für weitere Versionen, die nicht nur die Wiwi-Fakultät enthalten muss das gefixed werden
        $institutes = Institute::getInstitutes();
        $this->institutes = $institutes;

        $wiwiTree = StudipStudyArea::findOnebyStudip_object_id(Institute::findOneByName('Wirtschaftswissenschaftliche Fakultät')->id);
        $this->wiwiTree = $wiwiTree;

        $sub_wiwi = Institute::findByFaculty(Institute::findOneByName('Wirtschaftswissenschaftliche Fakultät')->id);
        $this->sub_wiwi = $sub_wiwi;

        //TODO: Geht noch nicht!
        $abwl_members = array();
        foreach ($sub_wiwi as $currentSub) {
            //$abwl_members = $sub_wiwi[$this->ABWL_INDEX]->members;

            //array_merge($abwl_members, $currentSub->members);
        }
        $this->abwl_members = $abwl_members;

        $studysubjects = StudipStudyArea::findByParent($wiwiTree->id);
        $this->studysubjects = $studysubjects;

        $poBAE = array();
        $tmp = array();
        $poBAE1 = StudipStudyArea::findByParent($studysubjects[$this->BAE_INDEX]->id);
        foreach ($poBAE1 as $item){
            if($item->name==="Studien- und Prüfungsordnung")
                $tmp = StudipStudyArea::findByParent($item->id);
        }
        foreach ($tmp as $p)
            array_push($poBAE, $p->name);

        $this->poBAE = $poBAE;
    }

    /**
     * Wird aufgerufen, wenn man sich im 'ECTS-Liste erzeugen'-Menüpunkt befindet
     * Variablen für die Views werden festgelegt
     */
    public function ects_action(){
        Navigation::activateItem('/tools/modulkatalog/ects');

        $this->semesters = Semester::getAll();
        $this->current_semester = Semester::findCurrent();

        $wiwiTree = StudipStudyArea::findOnebyStudip_object_id(Institute::findOneByName('Wirtschaftswissenschaftliche Fakultät')->id);
        $this->wiwiTree = $wiwiTree;

        $sub_wiwi = Institute::findByFaculty(Institute::findOneByName('Wirtschaftswissenschaftliche Fakultät')->id);
        $this->sub_wiwi = $sub_wiwi;

        $abwl_members = $sub_wiwi[$this->ABWL_INDEX]->members;
        $this->abwl_members = $abwl_members;

    }

    /**
     * Funktion für das JavaScript um in der ECTS-Funktion bei Veränderung
     * des Lehrstuhls die Lehrenden zu aktualisieren
     */
    public function populateProfs_action(){
        //TODO: Geht noch nicht wenn mehrere ausgewählt!
        $idarray = array();
        $members = array();
        $singleID = Request::get("id");
        $multipleID = Request::getArray("id");
        //$multipleID[0] = "118a87e48121f4ed86ad5c9545d5e720";

        if($multipleID != null && sizeof($multipleID) > 0) {
            //array_merge($idarray, $multipleID);
            $idarray = $multipleID;
        }

        if($singleID != null) {
            array_push($idarray, $singleID);
        }

        foreach ($idarray as $currentID) {
            $currentMembers = Institute::find($currentID)->members;
            $this->render_json($currentMembers->toArray());
            //array_merge($members, $currentMembers);
            $members = $currentMembers;
        }
        //$members = Institute::find($singleID)->members;
        //$this->render_json($members->toArray());
    }

    /**
     * Funktion für das JavaScript um in der Modul-Funktion bei Veränderung
     * des Studiengangs die Prüfungsordnungsversionen zu aktualisieren
     */
    public function populateRegulation_action(){
        $studysubjects = StudipStudyArea::findByParent(
            StudipStudyArea::findOnebyStudip_object_id(Institute::findOneByName(
                'Wirtschaftswissenschaftliche Fakultät')->id)->id);
        foreach ($studysubjects as $studysubject) {
            if ($studysubject->name===Request::get("id")){
                if(Request::get("id")==="Bachelor Business Administration and Economics"||
                    Request::get("id")==="Bachelor Wirtschaftsinformatik"){
                    $tmp = StudipStudyArea::findByParent($studysubject->id);
                    foreach ($tmp as $item){
                        if($item->name==="Studien- und Prüfungsordnung")
                            $po = StudipStudyArea::findByParent($item->id);
                    }
                }
                else{
                    $po = StudipStudyArea::findByParent($studysubject->id);
                }
            }
        }
        $erg = array();
        foreach ($po as $item) {
            if(Request::get("id")==="Bachelor Business Administration and Economics" &&
                $item->name==="Version 1") {}
            else
                array_push($erg, $item->name);
        }
        $this->render_json($erg);

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


}

