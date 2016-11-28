<?php
$query1 = "SELECT * FROM ost_config WHERE `key`='export_pdf_format' ";
$data1=db_query($query1);
if($data1){
    $value1 = db_fetch_array($data1);
    $export_pdf_format = $value1['value'];
}
else{
    echo "not success";
}
$query1 = "SELECT * FROM ost_config WHERE `key`='export_txt_format' ";
$data1=db_query($query1);
if($data1){
    $value1 = db_fetch_array($data1);
    $export_txt_format = $value1['value'];
}
else{
    echo "not success";
}
$query1 = "SELECT * FROM ost_config WHERE `key`='export_word_format' ";
$data1=db_query($query1);
if($data1){
    $value1 = db_fetch_array($data1);
    $export_word_format = $value1['value'];
}
else{
    echo "not success";
}


if (!$ticket && $thisclient->isGuest())

    Http::redirect('view.php');

$nav->setActiveNav('export_tickets');

$inc='tickets.inc.php';


include(CLIENTINC_DIR.'header.inc.php');

//include(CLIENTINC_DIR.$inc);
?>

<?php
if(!defined('OSTCLIENTINC') || !is_object($thisclient) || !$thisclient->isValid()) die('Access Denied');

$settings = &$_SESSION['client:Q'];

// Unpack search, filter, and sort requests
if (isset($_REQUEST['clear']))
    $settings = array();
if (isset($_REQUEST['keywords'])) {
    $settings['keywords'] = $_REQUEST['keywords'];
}
if (isset($_REQUEST['topic_id'])) {
    $settings['topic_id'] = $_REQUEST['topic_id'];
}
if (isset($_REQUEST['status'])) {
    $settings['status'] = $_REQUEST['status'];
}

$org_tickets = $thisclient->canSeeOrgTickets();
if ($settings['keywords']) {
    // Don't show stat counts for searches
    $openTickets = $closedTickets = -1;
}
elseif ($settings['topic_id']) {
    $openTickets = $thisclient->getNumTopicTicketsInState($settings['topic_id'],
        'open', $org_tickets);
    $closedTickets = $thisclient->getNumTopicTicketsInState($settings['topic_id'],
        'closed', $org_tickets);
}
else {
    $openTickets = $thisclient->getNumOpenTickets($org_tickets);
    $closedTickets = $thisclient->getNumClosedTickets($org_tickets);
}

$tickets = Ticket::objects();

$qs = array();
$status=null;

$sortOptions=array('id'=>'number', 'subject'=>'cdata__subject',
                    'status'=>'status__name', 'dept'=>'dept__name','date'=>'created');
$orderWays=array('DESC'=>'-','ASC'=>'');
//Sorting options...
$order_by=$order=null;
$sort=($_REQUEST['sort'] && $sortOptions[strtolower($_REQUEST['sort'])])?strtolower($_REQUEST['sort']):'date';
if($sort && $sortOptions[$sort])
    $order_by =$sortOptions[$sort];

$order_by=$order_by ?: $sortOptions['date'];
if ($_REQUEST['order'] && $orderWays[strtoupper($_REQUEST['order'])])
    $order = $orderWays[strtoupper($_REQUEST['order'])];
else
    $order = $orderWays['DESC'];

$x=$sort.'_sort';
$$x=' class="'.strtolower($_REQUEST['order'] ?: 'desc').'" ';

$basic_filter = Ticket::objects();
if ($settings['topic_id']) {
    $basic_filter = $basic_filter->filter(array('topic_id' => $settings['topic_id']));
}

if ($settings['status'])
    $status = strtolower($settings['status']);
    switch ($status) {
    default:
        $status = 'open';
    case 'open':
    case 'closed':
		$results_type = ($status == 'closed') ? __('Closed Tickets') : __('Open Tickets');
        $basic_filter->filter(array('status__state' => $status));
        break;
}

// Add visibility constraints — use a union query to use multiple indexes,
// use UNION without "ALL" (false as second parameter to union()) to imply
// unique values
$visibility = $basic_filter->copy()
    ->values_flat('ticket_id')
    ->filter(array('user_id' => $thisclient->getId()))
    ->union($basic_filter->copy()
        ->values_flat('ticket_id')
        ->filter(array('thread__collaborators__user_id' => $thisclient->getId()))
    , false);

if ($thisclient->canSeeOrgTickets()) {
    $visibility = $visibility->union(
        $basic_filter->copy()->values_flat('ticket_id')
            ->filter(array('user__org_id' => $thisclient->getOrgId()))
    , false);
}

// Perform basic search
if ($settings['keywords']) {
    $q = trim($settings['keywords']);
    if (is_numeric($q)) {
        $tickets->filter(array('number__startswith'=>$q));
    } elseif (strlen($q) > 2) { //Deep search!
        // Use the search engine to perform the search
        $tickets = $ost->searcher->find($q, $tickets);
    }
}

$tickets->distinct('ticket_id');

TicketForm::ensureDynamicDataView();

$total=$visibility->count();
$page=($_GET['p'] && is_numeric($_GET['p']))?$_GET['p']:1;
$pageNav=new Pagenate($total, $page, PAGE_LIMIT);
$qstr = '&amp;'. Http::build_query($qs);
$qs += array('sort' => $_REQUEST['sort'], 'order' => $_REQUEST['order']);
$pageNav->setURL('tickets.php', $qs);
$tickets->filter(array('ticket_id__in' => $visibility));
$pageNav->paginate($tickets);

$showing =$total ? $pageNav->showing() : "";
if(!$results_type)
{
	$results_type=ucfirst($status).' '.__('Tickets');
}
$showing.=($status)?(' '.$results_type):' '.__('All Tickets');
if($search)
    $showing=__('Search Results').": $showing";

$negorder=$order=='-'?'ASC':'DESC'; //Negate the sorting

$tickets->order_by($order.$order_by);
$tickets->values(
    'ticket_id', 'number', 'created', 'isanswered', 'source', 'status_id',
    'status__state', 'status__name', 'cdata__subject', 'dept_id',
    'dept__name', 'dept__ispublic', 'user__default_email__address'
);

?>

<h1 style="margin:10px 0">
   Export Tickets to file formats
   <div class="pull-right states">
        <?php if($export_word_format){ ?>
        <a href="assets/ticketlist.docx" download>Export List(Word)</a>&nbsp;| &nbsp;
        <?php } ?>
        <?php if($export_txt_format){ ?>
        <a href="assets/ticketlist.txt" download>Export List(txt)</a>
        <?php } ?>
   </div>
</h1>
<table id="ticketTable" width="800" border="0" cellspacing="0" cellpadding="0">
    <caption><?php echo $showing; ?></caption>
    <thead>
        <tr>
            <th nowrap>
                <a href="tickets.php?sort=ID&order=<?php echo $negorder; ?><?php echo $qstr; ?>" title="Sort By Ticket ID"><?php echo __('Ticket #');?></a>
            </th>
            <th width="120">
                <a href="tickets.php?sort=date&order=<?php echo $negorder; ?><?php echo $qstr; ?>" title="Sort By Date"><?php echo __('Create Date');?></a>
            </th>
            <th width="100">
                <a href="tickets.php?sort=status&order=<?php echo $negorder; ?><?php echo $qstr; ?>" title="Sort By Status"><?php echo __('Status');?></a>
            </th>
            <th width="320">
                <a href="tickets.php?sort=subj&order=<?php echo $negorder; ?><?php echo $qstr; ?>" title="Sort By Subject"><?php echo __('Subject');?></a>
            </th>
            <th width="120">
                <a href="tickets.php?sort=dept&order=<?php echo $negorder; ?><?php echo $qstr; ?>" title="Sort By Department"><?php echo __('Department');?></a>
            </th>
            <?php if($export_pdf_format){ ?>
            <th width="120">
                <?php echo __('Export');?>
            </th>
            <?php } ?>
            <?php if($export_txt_format){ ?>
            <th width="120">
                <?php echo __('Export');?>
            </th>
            <?php } ?>
            <?php if($export_word_format){ ?>
            <th width="120">
                <?php echo __('Export');?>
            </th>
            <?php } ?>
        </tr>
    </thead>
    <tbody>
    <?php
     $subject_field = TicketForm::objects()->one()->getField('subject');
     $defaultDept=Dept::getDefaultDeptName(); //Default public dept.
     
     if ($tickets->exists(true)) {
        $txtlist='';
         foreach ($tickets as $T) {
            $dept = $T['dept__ispublic']
                ? Dept::getLocalById($T['dept_id'], 'name', $T['dept__name'])
                : $defaultDept;
            $subject = $subject_field->display(
                $subject_field->to_php($T['cdata__subject']) ?: $T['cdata__subject']
            );
            $status = TicketStatus::getLocalById($T['status_id'], 'value', $T['status__name']);
            if (false) // XXX: Reimplement attachment count support
                $subject.='  &nbsp;&nbsp;<span class="Icon file"></span>';

            $ticketNumber=$T['number'];
            if($T['isanswered'] && !strcasecmp($T['status__state'], 'open')) {
                $subject="<b>$subject</b>";
                $ticketNumber="<b>$ticketNumber</b>";
            }
            ?>
            <tr id="<?php echo $T['ticket_id']; ?>">
                <td>
                <a class="Icon <?php echo strtolower($T['source']); ?>Ticket" title="<?php echo $T['user__default_email__address']; ?>"
                    href="tickets.php?id=<?php echo $T['ticket_id']; ?>"><?php echo $ticketNumber; ?></a>
                </td>
                <td><?php echo Format::date($T['created']); ?></td>
                <td><?php echo $status; ?></td>
                <td>
                    <div style="max-height: 1.2em; max-width: 320px;" class="link truncate" href="tickets.php?id=<?php echo $T['ticket_id']; ?>"><?php echo $subject; ?></div>
                </td>
                <td><span class="truncate"><?php echo $dept; ?></span></td>
                <?php if($export_pdf_format){ ?>
                <td><a href="tickets.php?id=<?php echo $T['ticket_id']; ?>&a=print">Pdf</a></td>
                <?php } ?>
                <?php if($export_txt_format){ ?>
                <td><a href="assets/ticket<?php echo $ticketNumber; ?>.txt" download>Txt</a></td>
                <?php } ?>
                <?php if($export_word_format){ ?>
                <td><a href="assets/ticket<?php echo $ticketNumber; ?>.docx" download>Word</a></td>
                <?php } ?>
            </tr>
        <?php
        //Write txt files
        $myfile = fopen("assets/ticket".$ticketNumber.".txt", "w") or die("Unable to open file!");
        $txt = "Ticket Id: ".$T['ticket_id'];
        $txt.= "\r\nTicket Number: ".$ticketNumber;        
        //$txt.= "\r\nName: ";
        $txt.= "\r\nEmail: ".$T['user__default_email__address'];
        //$txt.= "\r\nPhone: ";
        //$txt.= "\r\nSource: ";
        $txt.= "\r\nStatus: ".$T['status__name'];
        //$txt.= "\r\nPriority: ";
        $txt.= "\r\nDepartment: ".$T['dept__name'];
        $txt.= "\r\nCreate Date: ".$T['created'];
        $txt.= "\r\nSummary: ".$T['cdata__subject'];
        //$txt.= "\r\nDetails: ";
        //echo "<br/>".$txt;
        fwrite($myfile, $txt);
        fclose($myfile);
        $txtlist.=$txt;
        $txtlist.="\r\n______________________________________________________\r\n";
        //End Write txt files
        //=================================================================================================================
        //Write Word files
        // Creating the new document...
        $phpWord = new \PhpOffice\PhpWord\PhpWord();


        /* Note: any element you append to a document must reside inside of a Section. */

        // Adding an empty Section to the document...
        $section = $phpWord->addSection();
        // Adding Text element to the Section having font styled by default...
        $section->addText("Ticket Id: ".$T['ticket_id']);
        $section->addText("\r\nTicket Number: ".$ticketNumber);
        $section->addText("\r\nEmail: ".$T['user__default_email__address']);
        $section->addText("\r\nStatus: ".$T['status__name']);
        $section->addText("\r\nDepartment: ".$T['dept__name']);
        $section->addText("\r\nCreate Date: ".$T['created']);
        $section->addText("\r\nSummary: ".$T['cdata__subject']);

        /*
         * Note: it's possible to customize font style of the Text element you add in three ways:
         * - inline;
         * - using named font style (new font style object will be implicitly created);
         * - using explicitly created font style object.
         */

        // Adding Text element with font customized inline...
        

        // Saving the document as OOXML file...
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save('assets/ticket'.$ticketNumber.'.docx');

        // Saving the document as ODF file...
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'ODText');
        $objWriter->save('assets/ticket'.$ticketNumber.'.odt');

        // Saving the document as HTML file...
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
        $objWriter->save('assets/ticket'.$ticketNumber.'.html');

        /* Note: we skip RTF, because it's not XML-based and requires a different example. */
        /* Note: we skip PDF, because "HTML-to-PDF" approach is used to create PDF documents. */
        }
        //echo $txtlist
        $myfile = fopen("assets/ticketlist.txt", "w") or die("Unable to open file!");
        fwrite($myfile, $txtlist);
        fclose($myfile);

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $section->addText($txtlist);
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save('assets/ticketlist.docx');
     } else {
         echo '<tr><td colspan="5">'.__('Your query did not match any records').'</td></tr>';
     }
    ?>
    </tbody>
</table>
<?php
if ($total) {
    echo '<div>&nbsp;'.__('Page').':'.$pageNav->getPageLinks().'&nbsp;</div>';
}
?>