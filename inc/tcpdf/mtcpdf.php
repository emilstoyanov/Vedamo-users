<?php
include('tcpdf.php');

class mTCPDF extends TCPDF {
    
    public $header_data = [];     
    public $footer_data = [];
    public $masterId    = null;
    public $left        = 0;
    public $top         = 0;
    public $bottom      = 0;
    public $sumMaster   = [];
    public $sumTotal    = [];
    public $autoHeader  = true;
    public $autoFooter  = true;


    /** TextOut() - Prints a text with the given params
     * 
     * @param INT $x          Left corner of the rect
     * @param INT $y          Top corner of the rect
     * @param FLOAT $w        Width
     * @param STRING $txt     The text to be writen
     * @param MIXED $border   Border width
     * @param STRING $align   Alignment of the text in the rect
     * @param BOOLEAN $fill   Indicates if the cell background must be painted (true) or transparent (false).
     */
    public function TextOut($x,$y,$w,$txt='',$border=0,$align='L',$fill=false) {
        $this->SetXY($x,$y);
        $this->Cell($w,0,$txt,$border,0,$align,$fill);
    }
    
    /** Multiline() - Prints a multiline text in the given width
     * 
     * @param type $x       Left corner of the rect
     * @param type $y       Top corner of the rect
     * @param type $w       Width
     * @param type $txt     The text to be writen
     * @param type $border  Border width
     * @param type $align   Alignment of the text in the rect
     */
    public function Multiline($x,$y,$w,$txt='',$border=0,$align='L',$fill=false) {
        $this->SetXY($x,$y);
        $this->MultiCell($w, 3, $txt, $border, $align, $fill);
    }
    
    /** setTopLeft() - Sets the top and left margins for the page
     * 
     * @param type $top     default 10
     * @param type $left    default 10
     */
    public function setTopLeft($top=10,$left=10) {
        $this->top = $top;
        $this->left = $left;       
    }
    
    /** Header() - Prints an auto header with the data in $header_data variable
     * 
     */
    public function Header() {
        if ($this->autoHeader) {
            $top = $this->top;
            $this->SetFont($this->header_data['font']['name'],$this->header_data['font']['style'],$this->header_data['font']['size']);
            $w = $this->getPageWidth()-5;
            $this->TextOut($this->left,$top,$w/2,$this->header_data['firmname'],0,$align='L');
            $this->TextOut($w/2,$top,$w/2,$this->header_data['objname'],0,$align='R');
            $top += 5;
            $this->TextOut($this->left,$top,$w/2,$this->header_data['listname'],0,$align='L');
            $this->TextOut($w/2,$top,$w/2,$this->header_data['filtername'],0,$align='R');
            $top += 5;
            if (isset($this->header_data['text'])&&($this->header_data['text']<>'')) {
                $this->TextOut($this->left,$top,0,$this->header_data['text'],0,$align='L');
                $top += 5;
            }        
            if (isset($this->header_data['text2'])&&($this->header_data['text2']<>'')) {
                $this->TextOut($this->left,$top,0,$this->header_data['text2'],0,$align='L');
                $top += 5;
            }        
            $left = $this->left;
            for ($i=0; $i<count($this->header_data['columns']); $i++) {
                $this->Multiline($left,$top,$this->header_data['columns'][$i]['width'],$this->header_data['columns'][$i]['title'],1,'C');
                $left += $this->header_data['columns'][$i]['width'];
            };
            $this->header_data['bottom'] = $this->GetY();
        }
    }
    
    /** Footer() - Prints a centered Footer of type "d.m.Y h:i:s     page No/total pages" , with settings in $footer_data variable
     * 
     */
    public function Footer() {
        if ($this->autoFooter) {
            $this->SetFont($this->footer_data['font']['name'],$this->footer_data['font']['style'],$this->footer_data['font']['size']);
            $this->TextOut(0,$this->getPageHeight()-7,0,date('d.m.Y h:i:s').'          '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(),0,'C');
        }
    }

    /** formatData() - Returns the given $val, formated with the $type
     * 
     * @param mixed  $val   string, UNIX timestamp or INT/FLOAT
     * @param string $type  'text' - nothing to do, just return $val
     *                      'date' - the $val is UNIX timestamp, should return formated $val with date('d.m.Y',$val) function
     *                      'number:2(count of digits)' - $val is float and should be formated with the given digits
     *                      'hidden' - for column hidden, displays X if the value is 1
     *                      'yesno'  - for columns of type 1 - Yes, 0 - No
     * @return string
     */
    public function formatData($val,$type) {
        if ($type == 'text') { return $val; }
        else if ($type == 'date') { if ($val) { return date('d.m.Y',$val); } else { return ''; } }
        else if ($type == 'hidden') { if ($val==1) { return 'X'; } else { return ''; } }
        else if ($type == 'yesno') { if ($val==1) { return 'ДА'; } else { return 'НЕ'; } }
        else if ($type == 'tmer') { if ($val==0) { return 'Топлоенергия'; } else { return 'Топла Вода'; } }
        else if ($type == 'mounth') { 
            switch ($val) {
                case 1 : return 'Януари'; break;
                case 2 : return 'Февруари'; break;
                case 3 : return 'Март'; break;
                case 4 : return 'Април'; break;
                case 5 : return 'Май'; break;
                case 6 : return 'Юни'; break;
                case 7 : return 'Юли'; break;
                case 8 : return 'Август'; break;
                case 9 : return 'Септември'; break;
                case 10 : return 'Октомври'; break;
                case 11 : return 'Ноември'; break;
                case 12 : return 'Декември'; break;
            }
        } else {
            if ($val == '') { 
                return '';                 
            } else {
                $digits = explode(':',$type)[1];
                return sprintf('%.'.$digits.'f',$val);        
            }
        }    
    }

    /** printListData() - Prints the rows given in $data, with the settings in $header_data variable, 
     *                    starting from header_data['bottom'] + 1 . NOTE that this function should be used 
     *                    ONLY with the SetAutoPageBreak(true,distance...) function !!!
     * 
     * @param array $data   can contain two types of data: 
     *                      - array(array(name=>value...),array(name=>value...),.... ) - comming from datatables
     *                      - array(stdClass,stdClass,..... ) - comming from php
     * @param boolean $footer - if is false, function draws a bottom line on each row
     */
    public function printListData($data,$footer=false) {

        $head = $this->header_data['columns'];
        $this->SetFont($this->header_data['font']['name'],'',8);
        $nexttop = $this->header_data['bottom'] + 1; $this->bottom = 0;
        $w = $this->getPageWidth()-5;
        foreach ($data as $row) {
            $left = $this->left; 
            for ($i=0; $i<count($head); $i++) {
                $fieldname = $head[$i]['name'];
                $this->Multiline($left,
                    $nexttop,
                    $head[$i]['width'],
                    $this->formatData( (is_array($row)) ? $row[$fieldname] : $row->$fieldname ,$head[$i]['type']),
                    0,
                    $head[$i]['align']
                );
                $left += $head[$i]['width'];
                $currY = $this->GetY();
                if ($currY > $this->bottom) { $this->bottom = $currY; }
            }  
            if (!$footer) { $this->Line($this->left,$nexttop-1,$w,$nexttop-1); };
            $nexttop = $this->getNextTop($this->bottom + 2);
        }

    }
    
    /** printList() - Prints a complete report with the given $header, $data and $footer
     * 
     * @param $header (array) an array with column data of type: name: column name in $data, title: title for the header, align: 'L','C' or 'R', width: number, type: see formatData()
     * @param $data (array) data for the report
     * @param $footer (array) an array with numbers for column summ
     */
    public function printList($header,$data,$footer=false) {
        
        $this->SetAutoPageBreak(true,0);
        $this->SetAuthor('Emil Stoyanov',true);
        $this->SetCreator('Dizart - online app',true);
        $this->SetTitle('Report',true);
        $this->setTopLeft(10,10);
        $this->SetMargins(10,10,5);    
        $this->header_data['columns'] = $header['cols'];
        $this->header_data['firmname'] = $header['firmname'];
        $this->header_data['listname'] = $header['listname'];
        $this->header_data['objname'] = $header['objname'];
        $this->header_data['filtername'] = $header['filtername'];
        $this->header_data['text'] = ( isset($header['text']) ? $header['text'] : '');
        $this->header_data['font'] = array('name'=>'freeserif','style'=>'B','size'=>10);
        $this->footer_data['font'] = array('name'=>'freeserif','style'=>'','size'=>6);
        $this->AddPage();
        $this->printListData($data);
        if ($footer) {
            $right = $this->left;        
            for ($i=0; $i<count($this->header_data['columns']); $i++) { $right += $this->header_data['columns'][$i]['width']; }
            $this->header_data['bottom'] = $this->GetY() + 1;
            $this->printListData($footer,true);
        }
        $this->Output('Report.pdf');			
        
    }

    private function getNextTop($nexttop) {
        if ($nexttop >= $this->getPageHeight()-10) { 
            $this->AddPage();
            $this->bottom = 0;
            return $this->header_data['bottom'] + 1; 
        } else { return $nexttop; }
    }

    private function printSumRow($data,$nexttop) {
        $head = $this->header_data['columns'];
        $w = $this->getPageWidth()-5;
        foreach ($data as $name=>$value) {
            $left = $this->left; 
            for ($i=0; $i<count($head); $i++) { 
                if ($head[$i]['name'] != $name) {
                    $left += $head[$i]['width'];
                } else {
                    $this->Line($this->left,$nexttop-1,$w,$nexttop-1);
                    $this->Multiline($left,$nexttop,$head[$i]['width'],$this->formatData($value,$head[$i]['type']),0,$head[$i]['align']);
                    $currY = $this->GetY();
                    if ($currY > $this->bottom) { $this->bottom = $currY; }
                }
            }
        }
        return $this->getNextTop($this->bottom + 2);
    }

    public function printMDData($data,$mId,$mName) {

        $head = $this->header_data['columns'];
        $this->SetFont($this->header_data['font']['name'],'',8);
        $nexttop = $this->header_data['bottom'] + 1; $this->bottom = 0;
        $w = $this->getPageWidth()-5;
        foreach ($data as $row) {
            $left = $this->left; 
            for ($i=0; $i<count($head); $i++) {
                $fieldname = $head[$i]['name'];
                $this->Multiline($left,
                    $nexttop,
                    $head[$i]['width'],
                    $this->formatData( (is_array($row)) ? $row[$fieldname] : $row->$fieldname ,$head[$i]['type']),
                    0,
                    $head[$i]['align']
                );
                $left += $head[$i]['width'];
                $currY = $this->GetY();
                if ($currY > $this->bottom) { $this->bottom = $currY; }
            }  
            $nexttop = $this->getNextTop($this->bottom + 2);
            if ($row[$mId] != $this->masterId) {
                // print sums of current master element and init array for the next one
                if (!empty($this->sumMaster)) { 
                    $nexttop = $this->printSumRow($this->sumMaster,$nexttop);
                    foreach ($this->sumMaster as $name=>&$value) { $value = 0; } 
                } else { $nexttop = $this->getNextTop($this->bottom + 2); };
                $this->Line($this->left,$nexttop-1,$w,$nexttop-1);
                $this->masterId = $row[$mId];
                $w = $this->getPageWidth()-5;
                $this->Multiline($this->left,$nexttop,$w,$row[$mName],0,'C');
                $currY = $this->GetY();
                if ($currY > $this->bottom) { $this->bottom = $currY; }
                $nexttop = $this->getNextTop($this->bottom + 2);
                $this->Line($this->left,$nexttop-1,$w,$nexttop-1);
            }
            // if we have columns to sum
            if (!empty($this->sumMaster)) { 
                foreach ($this->sumMaster as $name=>&$value) { $value += $row[$name]; }
                foreach ($this->sumTotal as $name=>&$value) { $value += $row[$name];  }
            }
        }
    }

    /** printMasterDetail() - Prints a complete master - detail report with the given $header, $data, $mId and $mName 
     * 
     * @param $header (array) an array with column data of type: name: column name in $data, title: title for the header, align: 'L','C' or 'R', width: number, type: see formatData()
     * @param $data (array) data for the report
     * @param $mId (string) the ID column for the master, the report will divide data when this collumn is changed
     * @param $mName (string) name of master element
     * @param $sumColumns (array) column names who we need to sum
     */
    public function printMasterDetail($header,$data,$mId,$mName,$sumColumns=[]) {
        
        $this->SetAutoPageBreak(true,0);
        $this->SetAuthor('Emil Stoyanov',true);
        $this->SetCreator('Dizart - online app',true);
        $this->SetTitle('Report',true);
        $this->setTopLeft(10,10);
        $this->SetMargins(10,10,5);    
        $this->header_data['columns'] = $header['cols'];
        $this->header_data['firmname'] = $header['firmname'];
        $this->header_data['listname'] = $header['listname'];
        $this->header_data['objname'] = $header['objname'];
        $this->header_data['filtername'] = $header['filtername'];
        $this->header_data['text'] = ( isset($header['text']) ? $header['text'] : '');
        $this->header_data['font'] = array('name'=>'freeserif','style'=>'B','size'=>10);
        $this->footer_data['font'] = array('name'=>'freeserif','style'=>'','size'=>6);
        $this->AddPage();
        // save current master ID fron first row of $data, then print master name at first row on first page
        $this->SetFont($this->header_data['font']['name'],'',8);
        $this->masterId = $data[0][$mId];
        $w = $this->getPageWidth()-5;
        $this->Multiline($this->left,$this->header_data['bottom'],$w,$data[0][$mName],0,'C');
        $this->header_data['bottom'] = $this->GetY()+1;
        $this->Line($this->left,$this->header_data['bottom']-1,$w,$this->header_data['bottom']-1);
        // init arrays with column`s sums
        if (!empty($sumColumns)) { foreach($sumColumns as $col) { $this->sumMaster[$col] = 0; $this->sumTotal[$col] = 0; } };
        $this->printMDData($data,$mId,$mName,$sumColumns);
        $currY = $this->GetY();
        if ($currY > $this->bottom) { $this->bottom = $currY; }
        $nexttop = $this->printSumRow($this->sumMaster,$this->bottom + 2);
        $this->printSumRow($this->sumTotal,$nexttop);
        $this->Output('Report.pdf');			
        
    }
    
}

?>