<?php
	@session_start();
	header("Cache-control:private");
	require_once('Connections/mohist.php');
	require_once('includes/functions.inc.php');
	include 'class_Db.php';
	include 'classes/class.files.php';
	$db = new Db;
$KT_authorizedUsers=" 1";
$KT_authFailedURL="forindex.php";
$KT_grantAccess=0;


if (isset($_SESSION["KT_Username"])) {
  if($_SESSION['last_access_time'] < time() - $GLOBALS['cfg']['LoginCookieValidity'])
  if (true || !(isset($_SESSION["KT_userAuth"])) || $_SESSION["KT_userAuth"]=="" || KT_strpos($KT_authorizedUsers, $_SESSION["KT_userAuth"])) {
    $KT_grantAccess = 1;
  }
}
if (!$KT_grantAccess) {
  $KT_qsChar = "?";
  if (strpos($KT_authFailedURL, "?")) $KT_qsChar = "&";
  $KT_referrer = $_SERVER["REQUEST_URI"];
  $KT_authFailedURL = $KT_authFailedURL . $KT_qsChar . "accessdenied=" . urlencode($KT_referrer);
  KT_redir($KT_authFailedURL);
}

$username=$_SESSION["KT_Username"];
$query_Recordset0 = "SELECT sn.*,wp.powerlevel FROM workpeople wp
					 left join store_name sn ON sn.store_sernum = wp.store_sernum
					 WHERE wp.work_id = '$username'";
$Recordset0 = $mohist->SelectLimit($query_Recordset0) or die($mohist->ErrorMsg());
$store_sernum		= $Recordset0->Fields('store_sernum');
$powerlevel			= $Recordset0->Fields('powerlevel');
$combin_yn			= $Recordset0->Fields('combin_yn');
$DN_auto_num 		= $Recordset0->Fields('DN_auto_num');
$add_line_yn 		= $Recordset0->Fields('add_line_yn');
$PSI				= $Recordset0->Fields('PSI');
$department_used	= $Recordset0->Fields('department_used');
$product_html		= $Recordset0->Fields('product_html');
$limit_q_open		= $Recordset0->Fields('limit_q_open');
$pay_VISA			= $Recordset0->Fields('pay_VISA');
$pay_ATM			= $Recordset0->Fields('pay_ATM');
$pay_ibon			= $Recordset0->Fields('pay_ibon');
$pay_webATM			= $Recordset0->Fields('pay_webATM');

$catalog = 'add';

//取得廠商所有上層類別
$btCondition = array('equal'=>array('store_sernum'=>$store_sernum,'deleted'=>0));
$btDatas = $db->GetList('product_bcate',$btCondition);
if(!empty($btDatas))
{
	foreach($btDatas as $k=>$v)
	{
		$btList[$v['Bcate_sernum']] = $v['product_Bcate_name'];
	}
}

if(!$btList){echo '<script>alert("請先設定上層類別");location.href="product_Bcate.php"</script>';exit;}

//取得廠商所有子類別
$ctCondition = array('equal'=>array('store_sernum'=>$store_sernum,'deleted'=>0));
$ctDatas = $db->GetList('product_cate',$ctCondition);
if(!empty($ctDatas))
{
	foreach($ctDatas as $k=>$v)
	{
		$ctList[$v['Bcate_sernum']][$v['cate_sernum']] = $v['product_cate_name'];
	}
}
if(!$ctList){echo '<script>alert("請先設定子類別");location.href="product_cate.php"</script>';exit;}

if(!empty($_POST['app']))
{
	$p_id	= $_POST['p_id'];
	$app 	= $_POST['app'];
	$app['store_sernum'] = $_POST['store_sernum'];
	$app['ibon']		 = (int)$app['ibon'];
	$app['up_type']		 = 0;
	if(!empty($_FILES['app']['name']['app_pic_index']))
	{
		$file = new Files;
		$PicDatas = $_FILES['app'];
		
		$limit = array("extension" => array("jpg","JPG","JPEG","jpeg","PNG","png"));
		
		$pics['app_pic_index']['name'] = $PicDatas['name']['app_pic_index'];
		$pics['app_pic_index']['type'] = $PicDatas['type']['app_pic_index'];
		$pics['app_pic_index']['tmp_name'] = $PicDatas['tmp_name']['app_pic_index'];
		$pics['app_pic_index']['size'] = $PicDatas['size']['app_pic_index'];
		
		foreach($pics as $k=>$v)
		{
			$Path = 'upload/'.$store_sernum;
			if($k == 'app_pic_index')
			{
				$fName = $file->upload_resize($v, $Path, $thumb_h,$thumb_w, 2, $limit);
				$app['app_pic_index'] = $Path."/".$fName;
			}
		}
	}
	else{
		$app['app_pic_index']	= $_POST[old][app_pic_index];
	}
	if($app['setcontent_type']==0){
		for($a=1;$a<=10;$a++){
			if(!empty($_FILES['app']['name']['app_pic_big'.$a]))
			{
				$file = new Files;
				$PicDatas = $_FILES['app'];
				$limit = array("extension" => array("jpg","JPG","JPEG","jpeg","PNG","png"));
				$pics['app_pic_big'.$a]['name'] = $PicDatas['name']['app_pic_big'.$a];
				$pics['app_pic_big'.$a]['type'] = $PicDatas['type']['app_pic_big'.$a];
				$pics['app_pic_big'.$a]['tmp_name'] = $PicDatas['tmp_name']['app_pic_big'.$a];
				$pics['app_pic_big'.$a]['size'] = $PicDatas['size']['app_pic_big'.$a];
				foreach($pics as $k=>$v)
				{
					$Path = 'upload/'.$store_sernum;
					if($k == 'app_pic_big'.$a)
					{
						$fName = $file->upload_resize($v, $Path, "540","220", 2, $limit);
						$app['app_pic_big'.$a] = $Path."/".$fName;
					}
				}
			}
			else{
				$app['app_pic_big'.$a]	= $_POST[old]['app_pic_big'.$a];
			}
		}
	}
	$spCondition = array('equal'=>array('product_sernum'=>$p_id));
	$db->Update('product_twanga',$app,$spCondition);
	
	if(!empty($_POST['dep'])){
		$del_psl = "DELETE FROM product_department WHERE store_sernum='$store_sernum' and product_sernum='$p_id'";
		$mohist->Execute($del_psl) or die($mohist->ErrorMsg());
		foreach($_POST['dep'] as $a){
			$insertSQL = sprintf("INSERT INTO product_department (store_sernum, department, product_sernum) VALUES (%s, %s, %s)",
                       GetSQLValueString($store_sernum, "text"),
                       GetSQLValueString($a, "text"),
					   GetSQLValueString($p_id, "text"));
			$mohist->Execute($insertSQL) or die($mohist->ErrorMsg());		
		}
	}
	if(!empty($_POST['des'])){
		$del_psl = "DELETE FROM product_setstore WHERE store_sernum='$store_sernum' and product_sernum='$p_id'";
		$mohist->Execute($del_psl) or die($mohist->ErrorMsg());
		foreach($_POST['des'] as $a){
			$insertSQL = sprintf("INSERT INTO product_setstore (store_sernum, store_otherID, product_sernum) VALUES (%s, %s, %s)",
                       GetSQLValueString($store_sernum, "text"),
                       GetSQLValueString($a, "text"),
					   GetSQLValueString($p_id, "text"));
			$mohist->Execute($insertSQL) or die($mohist->ErrorMsg());		
		}
	}
	if(!empty($_POST['pay'])){
		$del_psl = "DELETE FROM product_pay WHERE store_sernum='$store_sernum' and product_sernum='$p_id'";
		$mohist->Execute($del_psl) or die($mohist->ErrorMsg());
		foreach($_POST['pay'] as $a){
			$insertSQL = sprintf("INSERT INTO product_pay (store_sernum, pay_style, product_sernum) VALUES (%s, %s, %s)",
                       GetSQLValueString($store_sernum, "text"),
                       GetSQLValueString($a, "text"),
					   GetSQLValueString($p_id, "text"));
			$mohist->Execute($insertSQL) or die($mohist->ErrorMsg());		
		}
	}
	echo '
		<script>
			alert("商品修改成功");
			location.href = "product_set_up.php";
		</script>
	';
}
$powerlevel=(int)$Recordset0->Fields('powerlevel');
$powerlevel1=512;
$powerlevel2=$powerlevel&$powerlevel1;
if ($powerlevel2==512) {

$cate_sernum=$_POST['cate_sernum'];

if($_POST['is_copy']=='y'){
	$store_sernum_a=$_POST['product_sernum_a'];
	$query_Recordset130 = "SELECT pc.Bcate_sernum,pt.* 
						   FROM  product_twanga pt
						   LEFT JOIN product_cate pc ON pc.cate_sernum = pt.cate_sernum
						   WHERE product_sernum = '$store_sernum_a'";
	$Recordset130 		= $mohist->SelectLimit($query_Recordset130) or die($mohist->ErrorMsg());
	$Bcate_sernum_a 	= $Recordset130->Fields('Bcate_sernum');
	
	$query_pro = array('equal'=>array('store_sernum'=>$store_sernum,'product_sernum'=>$store_sernum_a));
	$gp = $db->GetData('product_twanga',$query_pro);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>產品設定</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<script type="text/javascript" src="javascript/jquery.js"></script>
<script type="text/javascript" src="javascript/cal.js" charset="BIG5"></script>
<link href="css/calendar.css" rel="stylesheet" type="text/css" />
<script>
$(document).ready(function() {
	$("#start").simpleDatepicker({startdate: 2008});
	$("#end").simpleDatepicker({startdate: 2008});
	$("#specialdate").simpleDatepicker({startdate: 2008});
	$("#tospecialdate").simpleDatepicker({startdate: 2008});
	<?php 
		if($gp[ibon]==1){
	?>
		$("#ibon_title").show();
		$("#ibon_content").show();
		$("#ibon_item").show();
		$("#ibon_name").show();
	<?php 
		}else{
	?>
		$("#ibon_title").hide();
		$("#ibon_content").hide();
		$("#ibon_item").hide();
		$("#ibon_name").hide();
	<?php }?>
	$("input[name='used_type']").change(function(){
		$("#typeForm").submit();	
	})
	$('#Bcate_sernum').change(function(){
		var btID = parseInt($(this).val());
		switch(btID)
		{
			<?php foreach($ctList as $k=>$v):?>
			case <?php echo $k;?>:
				  $("#cate_sernum option").remove();
				  $("#cate_sernum").append('<option value="" selected>請選擇</option>');
				  <?php foreach($v as $k1=>$v1):?>
				  $("#cate_sernum").append('<option value="<?php echo $k1;?>"><?php echo $v1;?></option>');
				  <?php endforeach;?>
				  break;
			<?php endforeach;?>
		}
	})
	$(document).ready(function(){
	$("#prices_sp").change(function(){
		var t1 = parseInt($("#prices").val());
		var t2 = parseInt($(this).val());
		if(t1)
		{
			$("#msg1").remove();
			var msga = t2/t1*10;
				msg = msga.toFixed(1);
			$("#msg").append("<span id='msg1'>"+msg+"折</span>");
		}
		})
	})
$("#QR_code").submit(function(){

		var num = /^[0-9]+$/;
		var send = false;
		
		var time_type = /^(\d{1,2})(:)?(\d{1,2})\2(\d{1,2})$/;//時間格式
		var chk_starttime = time_type.test($("#starttime").val());
	<?php if($store_sernum != 'SWM'){?>
		if ($("#starttime").val()!='' && chk_starttime == false)
		{
			alert("起始日期時間格式錯誤(hh:mm:ss)");
			return false
		}
		var chk_endtime = time_type.test($("#endtime").val());
		if ($("#endtime").val()!='' && chk_endtime == false)
		{
			alert("截止日期時間格式錯誤(hh:mm:ss)");
			return false
		}
		
		if($("#Bcate_sernum").val() == '')
		{
			alert("請選擇上層類別");
			return false;	
		}
		if($("#cate_sernum").val() == '')
		{
			alert("請選擇產品子類別");
			return false;	
		}
		if($("#product_name").val()=='')
		{
			alert("產品名稱請勿空白");
			return false;
		}
		if($("#app_num").val()=='')
		{
			alert("請設定銷售數量");
			return false;
		}
		if($("#app_num").val()<='0')
		{
			alert("銷售數量請輸入大於0的數字");
			return false;
		}
		else
		{
			if(!num.test($("#app_num").val())){
				alert("銷售數量,請輸入數字,請勿輸入全形數字或文字符號!!");
				return false;
			}
		}		
		if($("#start").val=='' || $("#end").val()=='')
		{
			alert("銷售日期請填寫完整");
			return false;
		}
				
		if($("#prices").val()=='')
		{
			alert("請輸入商品原價");
			return false;
		}
		if($("#prices").val()<='0')
		{
			alert("商品原價請輸入大於0的數字");
			return false;
		}
		else
		{
			if(!num.test($("#prices").val())){
				alert("商品原價,請輸入數字,請勿輸入全形數字或文字符號!!");
				return false;
			}
		}
		if($("#prices_sp").val()=='')
		{
			alert("請設定團購價格");
			return false;
		}
		if($("#prices_sp").val()<='0')
		{
			alert("團購價格請輸入大於0的數字");
			return false;
		}
		else
		{
			if(!num.test($("#prices_sp").val())){
				alert("團購價格,請輸入數字,請勿輸入全形數字或文字符號!!");
				return false;
			}
		}
		if(send)
		{
			alert("兌換券內容請勿空白");
			return false;	
		}
		if($("#app_snum").val() != '' &$("#app_snum").val() < '1')
		{
		alert("請輸入大於1的正整數");
		return false;			
		}		
		else
		{
			if($("#app_snum").val() != '' & $("#app_snum").val() > '1')
			{
				if($("#prices_sp").val() % $("#app_snum").val()!=0)	
				{
				alert("商品金額無法整除其套裝數，請重新輸入");
				return false;
				}
			}
		}
	<?php }?>
	$("#start").attr("disabled",false); 
	$("#starttime").attr("disabled",false); 
	$("#end").attr("disabled",false); 
	$("#endtime").attr("disabled",false); 
	$("#app_num").attr("disabled",false); 
	});	
	
	})
var q = function(id){return document.getElementById(id) || null};
function dispalyA3()
{
  var divElem = q('a12');
  var inp = divElem.getElementsByTagName('input');
  var A3 = q('a3');
  var A4 = q('a4');
  if (divElem != null)
  {
      if (inp[1].checked) 
      {
        A3.style.display = 'none';
      }
      else{
		A3.style.display = 'inline-block';
      }
  }
}
</script>
</head>

<body>
<div align='center'>
<?php include 'product_catalog.php';?>
<script language="JavaScript" type="text/JavaScript">
	function seep(){
		document.forms[0].action='see_product_detail.php';
		document.forms[0].target='_blank'; 
		document.forms[0].submit(); 
		document.forms[0].action='product_edit_new.php';
		document.forms[0].target='_top'; 
	} 
	function abgne(){document.getElementById("app_pic_index").outerHTML=document.getElementById("app_pic_index").outerHTML.replace(/value=\w/g,'');}
	function abgne1(){document.getElementById("app_pic_big1").outerHTML=document.getElementById("app_pic_big1").outerHTML.replace(/value=\w/g,'');}
	function abgne2(){document.getElementById("app_pic_big2").outerHTML=document.getElementById("app_pic_big2").outerHTML.replace(/value=\w/g,'');}
	function abgne3(){document.getElementById("app_pic_big3").outerHTML=document.getElementById("app_pic_big3").outerHTML.replace(/value=\w/g,'');}
	function abgne4(){document.getElementById("app_pic_big4").outerHTML=document.getElementById("app_pic_big4").outerHTML.replace(/value=\w/g,'');}
	function abgne5(){document.getElementById("app_pic_big5").outerHTML=document.getElementById("app_pic_big5").outerHTML.replace(/value=\w/g,'');}
	function abgne6(){document.getElementById("app_pic_big6").outerHTML=document.getElementById("app_pic_big6").outerHTML.replace(/value=\w/g,'');}
	function abgne7(){document.getElementById("app_pic_big7").outerHTML=document.getElementById("app_pic_big7").outerHTML.replace(/value=\w/g,'');}
	function abgne8(){document.getElementById("app_pic_big8").outerHTML=document.getElementById("app_pic_big8").outerHTML.replace(/value=\w/g,'');}
	function abgne9(){document.getElementById("app_pic_big9").outerHTML=document.getElementById("app_pic_big9").outerHTML.replace(/value=\w/g,'');}
	function abgne10(){document.getElementById("app_pic_big10").outerHTML=document.getElementById("app_pic_big10").outerHTML.replace(/value=\w/g,'');}

//判斷是某勾選不限定期間
function date_chahge() { 
	var oo=document.getElementById('no_date'); 
	if(oo.checked) 
	{ 
		document.QR_code.start.value='<?php echo date("Y-m-d")?>';
		document.QR_code.starttime.value='<?php echo date("H:i:s")?>';
		document.QR_code.end.value='2030<?php echo date("-m-d")?>';
		document.QR_code.endtime.value='<?php echo date("H:i:s")?>';
		
		document.QR_code.start.disabled=true;
		document.QR_code.starttime.disabled=true;
		document.QR_code.end.disabled=true;
		document.QR_code.endtime.disabled=true;
	}
	else
	{
		document.QR_code.start.value='';
		document.QR_code.starttime.value='';
		document.QR_code.end.value='';
		document.QR_code.endtime.value='';
		
		document.QR_code.start.disabled=false;
		document.QR_code.starttime.disabled=false;
		document.QR_code.end.disabled=false;
		document.QR_code.endtime.disabled=false;
	}
} 
//判斷是某勾選不限套數
function num_chahge() { 
	var oo=document.getElementById('no_num'); 
	if(oo.checked) 
	{ 
		document.QR_code.app_num.value='9999999';
		document.QR_code.app_num.disabled=true;
	}
	else
	{
		document.QR_code.app_num.value='';
		document.QR_code.app_num.disabled=false;
	}
} 
//判斷是否勾選優惠期限
function set_special() { 
	var oo=document.getElementById('is_special'); 
	if(oo.checked) 
	{ 
		document.QR_code.specialdate.disabled=false;
		document.QR_code.tospecialdate.disabled=false;
	}
	else
	{
		document.QR_code.specialdate.value='';
		document.QR_code.specialdate.disabled=true;
		document.QR_code.tospecialdate.value='';
		document.QR_code.tospecialdate.disabled=true;
	}
} 

$(document).ready(function(){
	<?php if($gp[trust_product]==1){ ?>
	$("#line_html").attr("disabled",true);
	<?php }	?>
	$("#trust_product").change(function(){
		if($("#trust_product").attr("checked")){
			alert("選擇實體票券，兌換券內容無法使用編輯器");
			$("#line_10").attr("checked","checked");
			$("#line_html").attr("disabled",true);
			$("#edit_line10").show();
			$("#edit_html").hide();
		}
		else{
			$("#line_10").attr("disabled",false);
			$("#line_html").attr("disabled",false);
		}
	})
	$("#qrcode_product").change(function(){
		if($("#qrcode_product").attr("checked")){
			$("#line_10").attr("disabled",false);
			$("#line_html").attr("disabled",false);
		}
		if($("#trust_product").attr("checked")){
			$("#line_10").attr("checked","checked");
			$("#line_html").attr("disabled",true);
			$("#edit_line10").show();
			$("#edit_html").hide();
		}
		else{
			$("#line_10").attr("disabled",false);
			$("#line_html").attr("disabled",false);
		}
	})
})

//判斷是否適用ibon取票
function ibon() { 
	var ii = function(id){return document.getElementById(id) || null};
	var oo =document.getElementById('set_ibon'); 
	var it = ii('ibon_title');
	var ic = ii('ibon_content');
	var im = ii('ibon_item');
	var ie = ii('ibon_name');
	if(oo.checked) 
	{ 
		it.style.display='inline-block';
		ic.style.display='inline-block';
		im.style.display='inline-block';
		ie.style.display='inline-block';
	}
	else
	{
		it.style.display='none';
		ic.style.display='none';
		im.style.display='none';
		ie.style.display='none';
	}
} 
</script>
<form name="QR_code" id="QR_code" method="POST" enctype="multipart/form-data" action="product_edit_new.php" onSubmit="return chk()">
	<table width="783px" border="0" cellpadding="3" cellspacing="0">
	<tr>
		<td align='center' width='114px'>選擇票券種類</td>
		<td valign='top' width='299px'>
		<input type="hidden" name="app[qrcode_used]" value="1">
		<?php if($PSI==1):?>
			<input type="checkbox" name="app[trust_product]" id="trust_product" value="1" <?php if($gp[trust_product]==1){echo 'checked';}?>>實體票券<br>
		<?php endif;?>
			<input type="checkbox" name="app[qrcode_product]" id="qrcode_product" value="1" <?php if($gp[qrcode_product]==1){echo 'checked';}?>>電子票券
			<?php if($pay_ibon==1){?>
			<style>
				#use_ibon{
					color 		: #ff0000;
					font-size	: 8pt;
				}
			</style>
			<br><span id='use_ibon'><input type='checkbox' id='set_ibon' name='app[ibon]' value='1' onclick='return ibon();' <?php if($gp[ibon]==1){echo 'checked';}?>>適用ibon取票</span>
			<?php }?>
		</td>
		<td width='60px'>&nbsp;</td>
		<td width='150px'>選擇該產品適用<br>付款方式</td>
		<td width='160px'>
		<?php
			$query_VISA = array('equal'=>array('store_sernum'=>$store_sernum,'product_sernum'=>$store_sernum_a,'pay_style'=>'VISA'));
			$g_VISA	= $db->GetList('product_pay',$query_VISA);
			$query_ATM = array('equal'=>array('store_sernum'=>$store_sernum,'product_sernum'=>$store_sernum_a,'pay_style'=>'ATM'));
			$g_ATM	= $db->GetList('product_pay',$query_ATM);
			$query_webA = array('equal'=>array('store_sernum'=>$store_sernum,'product_sernum'=>$store_sernum_a,'pay_style'=>'webA'));
			$g_webA	= $db->GetList('product_pay',$query_webA);
		?>
		<?php if($pay_VISA==1){?><input type='checkbox' name='pay[]' value='VISA' <?php if(count($g_VISA)==1){echo 'checked';}?>>刷卡付款<br><?php }?>
		<?php if($pay_ATM==1){?><input type='checkbox' name='pay[]' value='ATM' <?php if(count($g_ATM)==1){echo 'checked';}?>>ATM轉帳<br><?php }?>
		<?php if($pay_webATM==1){?><input type='checkbox' name='pay[]' value='webA' <?php if(count($g_webA)==1){echo 'checked';}?>>WebATM<?php }?>
		</td>
	</tr>
	</table>
	<table width="783" border="1" cellpadding="0" cellspacing="0" id="app_product">
		<tr>
			<td width="20%" align="right">上層類別</td>
			<td colspan="4"  align="left">
			<select id="Bcate_sernum">
				<option value="">請選擇</option>
				<?php foreach($btList as $k=>$v):?>
				<option value="<?php echo $k;?>" <?php if($Bcate_sernum_a == $k){echo 'selected';}?>><?php echo $v;?></option>
				<?php endforeach;?>
			</select>
			</td>
		</tr>
		<tr>
			<td align="right">產品子類別</td>
			<td colspan="4" align="left">
			<select name="app[cate_sernum]" id="cate_sernum">
				<option value="">請選擇</option>
				<?php
					if(!empty($ctList[$Bcate_sernum_a]) || isset($Bcate_sernum_a)):
					foreach($ctList[$Bcate_sernum_a] as $k=>$v):echo $v;
				?>
				<option value="<?php echo $k;?>" <?php if($prData['cate_sernum'] == $k){echo 'selected';}?>><?php echo $v;?></option>
				<?php 
					endforeach;
					endif;
				?>
             </select>
			</td>
		</tr>
		<tr>
			<td align="right">列表圖片</td>
			<td colspan="4" align="left">
				<input type="file" name="app[app_pic_index]" id="app_pic_index">
				<input type='hidden' name='old[app_pic_index]' value=<?php echo $gp[app_pic_index]?>>
				<INPUT TYPE="button" onclick="abgne();" value="清空">
				<a href="<?php echo $gp[app_pic_index]?>" target='_blank'><?php echo $gp[app_pic_index]?></a>
				<br>
				<font size='-1'>若沒有選擇其他圖片，則延用原有圖片</font>
			</td>
		</tr>
		<tr>
			<td><div align="right">產品名稱</div></td>
			<td colspan="4" align="left">
				<input name="app[product_name]" type="text" id="product_name" value="<?php echo $gp['product_name'];?>" size="80" maxlength="80"> 
			</td>
		</tr>
            <tr>
              <td><div align="right">產品敘述</div></td>
              <td colspan="4" align="left"> <input name="app[product_2thname]" type="text" value="<?php echo $gp['product_2thname'];?>" size="80" maxlength="80">
			  <font size="-2" color="#999999">選填</font></td>
            </tr>			
            <tr> 
              <td><div align="right">銷售套數<br>
					不限套數<input type="checkbox" name="no_num" id="no_num" onclick='return num_chahge();'></div></td>
              <td colspan="4" align="left"> 
				<input name="app[app_num]" type="number" id="app_num" min="1" value="<?php echo $gp['app_num'];?>" size="6" maxlength="6"> 
              <font size="-2" color="#999999">必填</font><br>
				<span id="a12">
				設定單套張數
					<input type="radio" name="a2" value="2" onclick="dispalyA3()" <?php if($gp['app_snum']>1){echo 'checked';}?>>是
					<input type="radio" name="a2" value="3" onclick="dispalyA3()" <?php if($gp['app_snum']==1){echo 'checked';}?>>否
					<span id="a3" <?php if($gp['app_snum']==1){echo 'style="display:none;"';}?>>
						，一套<input name="app[app_snum]" type="number" id="app_snum" style="width:40px;" value="<?php echo $gp['app_snum']?>"/>張
					</span>
				</span>
			  </td>
            </tr>
			<style>
				.mon_date{
					z-index : 9999;
				}
			</style>
           <tr>
            <td align="right">
				刊登期限<br>
				不限期間<input type="checkbox" name="no_date" id="no_date" onclick='return date_chahge();'>
			</td>
            <td colspan="4" align="left">
				<input type="text" size="9" name="app[app_start_day]" value="<?php echo $gp['app_start_day'];?>" id="start" class='mon_date'>
				<input type="text" name="app[app_start_time]" value="<?php echo $gp['app_start_time'];?>" id="starttime" size='6pt'> 至 
				<input type="text" size="9" name="app[app_end_day]" value="<?php echo $gp['app_end_day'];?>" id="end" class='mon_date'>
				<input type="text" name="app[app_end_time]" value="<?php echo $gp['app_end_time'];?>" id="endtime" size='6pt'>
			<font size="-2" color="#999999">前後日期不得為空(<?php echo date('Y-m-d')?>  <?php echo date('H:i:s')?>)</font></td>
           </tr>           
		   <tr>
				<?php 
					if($gp['is_special']==1){
						$is_special 	= 'checked';
						$specialdate	= $gp[specialdate];
						$tospecialdate	= $gp[tospecialdate];
					}
					else{
						$is_special 	= '';
						$specialdate	= 'disabled';
						$tospecialdate	= 'disabled';
					}
				?>
				<td align="right">
					設定優惠期限<input type="checkbox" name="app[is_special]" id="is_special" <?php echo $is_special?> onclick='return set_special();'>
				</td>	
				<td colspan="4" align="left">
					<input type="text" size="10" name="app[specialdate]" id="specialdate"  <?php echo $specialdate?>  class='mon_date'>至 
					<input type="text" size="10" name="app[tospecialdate]" id="tospecialdate"  <?php echo $tospecialdate?>  class='mon_date'>
				</td>
           </tr>
           <tr>
            <td align="right">原價</td>
            <td colspan="2" align="left"><input type="number" name="app[prices]" id="prices" value="<?php echo $gp['prices'];?>" size="10" maxlength="10" min="1"></td>
			<td align="right" width="20%">團購價</td>
            <td colspan="2" align="left"><input type="number" name="app[prices_sp]" id="prices_sp" value="<?php echo $gp['prices_sp'] ?>" size="10" maxlength="10" min="1">　<span id="msg"><span id="msg1"></span></span></td>
           </tr>
		    <?php if($DN_auto_num!=""):?>
            <tr>    
              <td><div align="right">票號編號：</div></td>
              <td colspan="2"><input name="app[DN_p_id]" type="text" value="<?php echo $gp['DN_p_id'];?>" size="20"></td>  
              <td><div align="right">票號起始流水號：</div></td>
              <td colspan="2"><input name="app[DN_p_start]" type="text" value="<?php echo $gp['DN_p_start'];?>" size="20" maxlength="<?php echo $DN_auto_num;?>"></td>
            </tr>
            <?php endif;?>
		    <?php 
				if($department_used==1){
					$department_sql = "SELECT * FROM department WHERE store_sernum = '$store_sernum'";
					$department_set = $mohist->Execute($department_sql) or die($mohist->ErrorMsg());
			?>
            <tr>    
              <td align="right">設定核銷部門</td>
			  <td colspan='4' align="left">
            <?php 
				$num = 1;
				while(!$department_set->EOF){
					$id		= $department_set->Fields('id');
					$code	= $department_set->Fields('code');
					$name	= $department_set->Fields('name');
			?>
				<span style="width:200px;float:left;">
				<input type='checkbox' name='dep[]' id='dep[]' value="<?php echo $id?>"><?php echo "(".$code.")".$name;?>
				</span>
			<?php
				if($num%3==0){echo '<br>';}
				$num++;
				$department_set->MoveNext();
				}
			}
			?>
			</td>
            </tr>
		    <?php 
				if($department_set==1){
					$department_sql = "SELECT * FROM store_other WHERE store_sernum = '$store_sernum'";
					$department_set = $mohist->Execute($department_sql) or die($mohist->ErrorMsg());
			?>
            <tr>    
              <td align="right">設定產品所屬館別<br>(複選)</td>
			  <td colspan='4' align="left">
            <?php 
				$num = 1;
				while(!$department_set->EOF){
					$set_name	= $department_set->Fields('set_name');
					$id			= $department_set->Fields('id');
			?>
				<span style="width:200px;float:left;">
				<input type='checkbox' name='des[]' id='des[]' value="<?php echo $id?>"><?php echo $set_name;?>
				</span>
			<?php
				if($num%3==0){echo '<br>';}
				$num++;
				$department_set->MoveNext();
				}
			}
			?>
			</td>
            </tr>
<script>
$(document).ready(function(){
 <?php if($gp[lineno_type]==0){ ?>
	$("#edit_line10").show();
	$("#edit_html").hide();
<?php }else{ ?>
	$("#edit_line10").hide();
	$("#edit_html").show();
 <?php } ?>
	$("#line_10").click(function(){
		alert("切換為制定格式");
		$("#edit_line10").show();
		$("#edit_html").hide();
	})
	$("#line_html").click(function(){
		alert("切換為編輯器模式");
		$("#edit_line10").hide();
		$("#edit_html").show();
	})
})
</script>
			<tr>
				<td colspan="8" align="center" bgcolor="#333333">
					<font color="#FFFFFF">兌換券內容<br>
					<font style="font-size:8pt">
					<input type="radio" name="app[lineno_type]" id='line_10' value="0" <?php if($gp[lineno_type]==0){echo 'checked';}?>>制定格式
					<input type="radio" name="app[lineno_type]" id='line_html' value="1" <?php if($gp[lineno_type]==1){echo 'checked';}?>>編輯器模式
					</font>
					</font>
				</td>
			</tr>
			<tr>
				<td colspan="8" align="center">
				<span id='edit_line10'>
				<?php 
				for($i=1;$i<=10;$i++){
					$i	= sprintf("%02d",$i);
				?>
					<input name="app[lineno<?php echo $i;?>]" type="text" value="<?php echo $gp['lineno'.$i];?>" size="4">&nbsp;&nbsp;
					<input name="app[lineindex<?php echo $i;?>]" type="text" value="<?php echo $gp['lineindex'.$i];?>" size="80"><br> 
				<?php 
				}
				?>
				<?php if($limit_q_open==1):?>
					追加欄位<br>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input name="app[lineindex11]" type="text" value="<?php echo $gp['lineindex11'];?>" size="80" maxlength="50"><br> 
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input name="app[lineindex12]" type="text" value="<?php echo $gp['lineindex12'];?>" size="80" maxlength="50"><br> 
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input name="app[lineindex13]" type="text" value="<?php echo $gp['lineindex13'];?>" size="80" maxlength="50"><br> 
				<?php endif;?>
				</span>
				<span id='edit_html'>		
					<textarea cols="80" id="lineno_html" name="app[lineno_html]" rows="10">
						<?php echo $gp['lineno_html'];?>
					</textarea>
					<?php 
						include_once "includes/ckeditor/ckeditor.php";
						$CKEditor = new CKEditor();
						$CKEditor->basePath = 'includes/ckeditor/';
						$CKEditor->replace("lineno_html");
					?>
				</span>
				</td>
			</tr>
<script>
$(document).ready(function(){
<?php if($gp[buyno_type]==1){ ?>
	$("#edit_buy").hide();
	$("#edit_htmlbuy").show();
<?php }else{ ?>
	$("#edit_buy").show();
	$("#edit_htmlbuy").hide();
<?php } ?>
	$("#buy_10").click(function(){
		alert("切換為制定格式");
		$("#edit_buy").show();
		$("#edit_htmlbuy").hide();
	})
	$("#buy_html").click(function(){
		alert("切換為編輯器模式");
		$("#edit_buy").hide();
		$("#edit_htmlbuy").show();
	})
})
</script>		
			<tr>
				<td colspan="8" align="center" bgcolor="#333333">
					<font color="#FFFFFF">必買特色<br>
					<font style="font-size:8pt">
					<input type="radio" name="app[buyno_type]" id='buy_10' value="0" <?php if($gp[buyno_type]==0){echo 'checked';}?>>制定格式
					<input type="radio" name="app[buyno_type]" id='buy_html' value="1" <?php if($gp[buyno_type]==1){echo 'checked';}?>>編輯器模式
					</font>
				</td>
			</tr>
			<tr>
				<td colspan="8" align="center">
				<span id='edit_buy'>
				<?php for($i=1;$i<=5;$i++):?>
					<input name="app[buyno<?php echo $i;?>]" type="text" value="<?php echo $gp['buyno'.$i];?>" size="4" maxlength="4">&nbsp;&nbsp;
					<input name="app[buyindex<?php echo $i;?>]" type="text" value="<?php echo $gp['buyindex'.$i];?>" size="80"><br> 
				<?php endfor;?>
				</span>
				<span id='edit_htmlbuy'>
					<textarea cols="80" id="buyno_html" name="app[buyno_html]" rows="10">
						<?php echo $gp['buyno_html'];?>
					</textarea>
					<?php 
						include_once "includes/ckeditor/ckeditor.php";
						$CKEditor = new CKEditor();
						$CKEditor->basePath = 'includes/ckeditor/';
						$CKEditor->replace("buyno_html");
					?>
				</span>
				</span>
				</td>
			</tr>
<script>
$(document).ready(function(){
<?php if($gp[setcontent_type]==0){ ?>
	$("#edit_content").show();
	$("#edit_textfield").hide();
<?php }else{ ?>
	$("#edit_content").hide();
	$("#edit_textfield").show();
<?php } ?>
	$("#setcontent_10").click(function(){
		alert("切換為制定格式");
		$("#edit_content").show();
		$("#edit_textfield").hide();
	})
	$("#setcontent_html").click(function(){
		alert("切換為編輯器模式");
		$("#edit_content").hide();
		$("#edit_textfield").show();
	})
})
</script>					
			<tr>
				<td colspan="8" align="center" bgcolor="#333333">
					<font color="#FFFFFF">商品介紹<br>
					<font style="font-size:8pt">
					<input type="radio" name="app[setcontent_type]" id='setcontent_10' value="0" <?php if($gp[setcontent_type]==0){echo 'checked';}?>>制定格式
					<input type="radio" name="app[setcontent_type]" id='setcontent_html' value="1" <?php if($gp[setcontent_type]==1){echo 'checked';}?>>編輯器模式
					</font>
					</font>
				</td>
			</tr>
			<tr>
				<td colspan="8" align="left">
				<span id='edit_content'>
				<font size="-2" color="#999999">　(至少上傳一張相片540*220)</font><font color="#FF0000" size="-1"><上傳圖片較多，等待時間也較長></font><br>
			<?php
				for($i=1;$i<=10;$i++){
			?>
				商品照片<?php echo $i.".";?>
					<input type="file" name="app[app_pic_big<?php echo $i;?>]" id="app_pic_big<?php echo $i;?>" size="40">
					<input type='hidden' name='old[app_pic_big<?php echo $i;?>]' value=<?php echo $gp['app_pic_big'.$i]?>>
					<INPUT TYPE="button" onclick="abgne<?php echo $i;?>();" value="清空">
					<a href="<?php echo $gp['app_pic_big'.$i]?>" target='_blank'><?php echo $gp['app_pic_big'.$i]?></a>
				<br>
				<font size='-1'>若沒有選擇其他圖片，則延用原有圖片</font>
				<br>
                <input name="app[stroec<?php echo $i;?>]" type="text" value="<?php echo $gp['stroec'.$i]?>" size="4" maxlength="4">&nbsp;&nbsp;
				<input name="app[stroecdex<?php echo $i;?>]" type="text" value="<?php echo $gp['stroecdex'.$i];?>" size="55" maxlength="55"><br> 
				<?php  
					}
				?>
				</span>
				<span id='edit_textfield'>
				<textarea cols="80" id="textfield" name="app[textfield]" rows="10"><?php echo $gp[textfield];?></textarea>
				<?php 
					include_once "includes/ckeditor/ckeditor.php";
					$CKEditor = new CKEditor();
					$CKEditor->basePath = 'includes/ckeditor/';
					$CKEditor->replace("textfield");
				?>
				</span>
				</td>
			</tr>
			<tr>
				<td align="right">
					<span id='ibon_item'>
						<font>
							ibon票面名稱
							<br>最多九字<a href="ibon_sample.jpg" target='_blank'>示意圖</a>
						</font>
					</span>
				</td>
				<td colspan="7" align="left">
					<span id='ibon_name'>
						<input type='text' name="app[ibon_name]" maxlength="9" size="55" value="<?php echo $gp[ibon_name];?>">
					</span>
				</td>
			</tr>
			<tr>
				<td colspan="8" align="center" bgcolor="#333333">
				<span id='ibon_title'>
					<font color="#FFFFFF">ibon注意事項(顯示於票面注意事項)
						<br>
						一行最多25字<a href="ibon_sample.jpg" target='_blank'><font color="#ffffff">示意圖</font></a>
					</font>
				</span>
				</td>
			</tr>
			<tr>
				<td colspan="8" align="center">
				<span id='ibon_content'>
				<?php for($i=1;$i<=4;$i++):?>
					<?PHP echo $i;?>　.
					<input name="app[ibon_detail<?php echo $i;?>]" type="text" value="<?php echo $gp['ibon_detail'.$i]?>" size="80" maxlength="25"><br> 
				<?php endfor;?>
				</span>
				</td>
			</tr>
			<tr>
				<td colspan="3" align="center"><input type="submit" value="修改"></td>
				<td colspan="3" width="50%" align="center"><input type="button" value="手機頁面預覽" onClick="seep()"></td>
			</tr>
		</table>
		<input name="store_sernum" type="hidden" id="store_sernum" value="<?php echo $store_sernum;?>">
		<input name="app[other_sernum]" type="hidden" id="other_sernum" value="<?php echo $store_sernum;?>">
		<input name="p_id" type="hidden" value="<?php echo $store_sernum_a;?>">
	</form>
</div>

</body>
</html>
<?php } else { echo '沒有權限！';}?>
