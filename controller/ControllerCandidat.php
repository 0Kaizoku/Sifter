<?php
require_once dirname(__DIR__) . '/Model/Candidat.php'; 
require_once dirname(__DIR__) .'/vendor/autoload.php';
require_once dirname(__DIR__) . '/Model/ComCandit.php';
require_once dirname(__DIR__) . '/Model/Education.php';

use Smalot\PdfParser\Parser;
class ControllerCandidat extends Candidat{


    function Update(){
        if(isset($_POST["sumbit"])){
        if(isset($_POST["nom"]) and !empty($_POST["nom"]) and isset($_POST["prenom"]) and !empty($_POST["prenom"])
        and   isset($_POST["ville"]) and !empty($_POST["ville"]) and isset($_POST["telephone"]) and !empty($_POST["telephone"])
        and isset($_POST["domaine"]) and !empty($_POST["domaine"])){
            $this->updateCandidat($_POST["nom"],$_POST["prenom"],$_POST["ville"],$_POST["telephone"],$_POST["domaine"],$_SESSION["id"]);
        }
    }
    }

    public function setChoix(){

        if(isset($_POST['ch'])){
            if(isset($_FILES['cv'])){
                if(!empty($_FILES['cv'])){
                $file=$_FILES['cv'];
                $file_data = file_get_contents($file['tmp_name']);
                
                $this->choixCv($file_data);
               }
            }
        }
    }
    public function getChoix(){
        
        $cv = $this->getCv();
        if(empty($cv)){
            return 0;
        }
        else{
                $filename = 'output.pdf';
        $file = fopen($filename, 'wb');
        fwrite($file, $cv);
        fclose($file);

    // Chemin d'accès au fichier PDF
    $file_path = '../view/output.pdf';
    // Créer une instance du parser
    $parser = new Parser();

    // Extraire le texte de toutes les pages
    $text = '';
    $pdf = $parser->parseFile($file_path);
    $pages = $pdf->getPages();
    foreach ($pages as $page) {
    $text .= $page->getText();   
    }
    // Afficher le texte
    $json = $this->trosfCv($text);
    return $json;
        }
    }

    public function score(){
        if(isset($_POST["sumbit"])){
        
        $score=$this->calculCompte()*0.3+$this->calculedu()*0.3;

        $this->setScore($_SESSION["id"],$score);
        }

    }

    public function calculCompte(){
        $comp=new ComCandidat(); 
        $nonEq=$comp->getCom($_SESSION["id"]);
        $equal= $comp->ContgetCom($_SESSION["id"],$_POST["domaine"]);
        $nonEq=$nonEq-$equal;
        $compScore = ($equal*2)+$nonEq;
        return $compScore;
    }      
    public function calculedu(){
        $educ= new EduCandidat();
        $educa= $educ->getEducation($_SESSION["id"]);
        $score=0;
        foreach($educa as $edc ){
        if($edc["nom"]=='Baccalaréat'){
            $score=$score;
        }else if ($edc["nom"]=='Licence'){
            $score=$score+1;
        }else if ($edc["nom"]=='Master'){
            $score=$score+2;
        }else if ($edc["nom"]=='Doctorat'){
            $score=$score+3;
        }else{
            $score=0;
        }
        return $score;
    }
    } 

    public function  culculExp(){
        $exp = new Exp();
        $exps = $exp->Expscore($_SESSION["id"]);
        $scor=0;
        $sd=0; // initialize the variable before the loop
        foreach($exps as $esp ){
            if($esp["durre"]<1){
                $sd=0;
            }else if($esp["durre"]<6){
                $sd=1;
            }else if($esp["durre"]<12){
                $sd=2;
            }else if($esp["durre"]<24){
                $sd=4;
            }else{
                $sd=6;
            }
            if($esp["nom"]=='Stage'){
                $sd=$sd;
            }else{
                $sd=$sd*2;
            }
            $scor=$scor+$sd;
        }
        return $scor;
    }

}