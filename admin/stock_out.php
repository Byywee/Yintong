<?php
/**
 * ECSHOP 库存管理----退货管理
 * ============================================================================
 * * 版权所有 2005-2012 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liubo $
 * $Id: goods.php 17217 2011-01-19 06:29:08Z liubo $
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
require_once(ROOT_PATH . '/' . ADMIN_PATH . '/includes/lib_goods.php');
include_once(ROOT_PATH . '/includes/cls_image.php');
$image = new cls_image($_CFG['bgcolor']);
$exc = new exchange($ecs->table('goods'), $db, 'goods_id', 'goods_name');


if($_REQUEST['act'] == 'stock_out')
{
	$smarty->assign('ur_here',      $_LANG['02_goods_stock_out']);
	$smarty->assign('full_page',    1);
	if($_REQUEST['one'] == 'stock_edit_one')
	{
		date_default_timezone_set('Asia/Shanghai');//设置时间为上海的时间
		$date = time();
		$goods_id = $_REQUEST['goods_id'];
		$out_stock = $_REQUEST['out_stock'];
		$out_stock1 ="update " .$ecs->table('goods'). " set goods_number = goods_number - $out_stock where goods_id ='$goods_id' ";
		$out_stock2 ="insert into " .$ecs->table('stock_change')." (goods_id,goods_stock_num,goods_time,goods_op_type) values($goods_id,$out_stock,$date,4)	";
		if($goods_id!=0&&$out_stock!=0)
		{
			$db -> query($out_stock1);
			$db -> query($out_stock2);
		}
	}
	if(isset($_POST['btnSubmit']))
	{
		date_default_timezone_set('Asia/Shanghai');//设置时间为上海的时间
		$date = time();
		$goods_id = $_POST['checkboxes'];
		$out_stock = array();
		foreach ($goods_id as $key=>$value)
		{
			$out_stock[] = ($_POST['out_stock_'.$_POST['checkboxes'][$key]]);
		}
		foreach ($goods_id as $key=>$value)
		{
			$out_stock1 ="update " .$ecs->table('goods'). " set goods_number = goods_number - $out_stock[$key] where goods_id ='$goods_id[$key]' ";				
			$out_stock2 ="insert into " .$ecs->table('stock_change')." (goods_id,goods_stock_num,goods_time,goods_op_type) values($goods_id[$key],$out_stock[$key],$date,4)";
			if($out_stock[$key]!="")
			{
				$db -> query($out_stock1);
				$db -> query($out_stock2);
			}
		}
	}
	$goods_list=stock_out();
	$a=$goods_list['goods'];
	foreach ($a as $key => $value)
	{
		$time="select max(goods_time) from " .$ecs->table('stock_change'). " where goods_id =".$value['goods_id']."";
		$time1=$db -> getOne($time);
		$time1=date('Y-m-d H:i:s',$time1);
		$a[$key]['goods_time'] = $time1;
	}
	$goods_list['goods']=$a;

	$smarty->assign('goods_list',   $goods_list['goods']);
	$smarty->assign('filter',       $goods_list['filter']);
	$smarty->assign('record_count', $goods_list['record_count']);
	$smarty->assign('page_count',   $goods_list['page_count']);
	 
	$smarty->assign('cat_list',     cat_list(0, $cat_id));//搜索所有分类
	$smarty->assign('brand_list',   get_brand_list());//搜索所有品牌
	
	assign_query_info();
	$smarty->display("stock_out.htm");
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
	$stock_out = stock_out();
	$smarty->assign('goods_list',   $stock_out['goods']);
	$smarty->assign('filter',       $stock_out['filter']);
	$smarty->assign('record_count', $stock_out['record_count']);
	$smarty->assign('page_count',   $stock_out['page_count']);
	make_json_result($smarty->fetch('stock_out.htm'), '',
	array('filter' => $stock_out['filter'], 'page_count' => $stock_out['page_count']));
}



/**
 * 获取商品列表
 *
 * @access  public
 * @return  array
 */

function stock_out()
{
	/* 过滤条件 */
	$param_str = '-' . $is_delete . '-' . $real_goods;
	$result = get_filter($param_str);
	if ($result === false)
	{
		$day = getdate();
		$today = local_mktime(23, 59, 59, $day['mon'], $day['mday'], $day['year']);

		$filter['cat_id']           = empty($_REQUEST['cat_id']) ? 0 : intval($_REQUEST['cat_id']);
		$filter['intro_type']       = empty($_REQUEST['intro_type']) ? '' : trim($_REQUEST['intro_type']);
		$filter['is_promote']       = empty($_REQUEST['is_promote']) ? 0 : intval($_REQUEST['is_promote']);
		$filter['stock_warning']    = empty($_REQUEST['stock_warning']) ? 0 : intval($_REQUEST['stock_warning']);
		$filter['brand_id']         = empty($_REQUEST['brand_id']) ? 0 : intval($_REQUEST['brand_id']);
		$filter['keyword']          = empty($_REQUEST['keyword']) ? '' : trim($_REQUEST['keyword']);
		$filter['suppliers_id'] = isset($_REQUEST['suppliers_id']) ? (empty($_REQUEST['suppliers_id']) ? '' : trim($_REQUEST['suppliers_id'])) : '';
		$filter['is_on_sale'] = isset($_REQUEST['is_on_sale']) ? ((empty($_REQUEST['is_on_sale']) && $_REQUEST['is_on_sale'] === 0) ? '' : trim($_REQUEST['is_on_sale'])) : '';
		if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
		{
			$filter['keyword'] = json_str_iconv($filter['keyword']);
		}
		$filter['sort_by']          = empty($_REQUEST['sort_by']) ? 'goods_id' : trim($_REQUEST['sort_by']);
		$filter['sort_order']       = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);
		$filter['extension_code']   = empty($_REQUEST['extension_code']) ? '' : trim($_REQUEST['extension_code']);
		$filter['is_delete']        = $is_delete;
		$filter['real_goods']       = $real_goods;

		$where = $filter['cat_id'] > 0 ? " AND " . get_children($filter['cat_id']) : '';

		/* 推荐类型 */
		switch ($filter['intro_type'])
		{
			case 'is_best':
				$where .= " AND is_best=1";
				break;
			case 'is_hot':
				$where .= ' AND is_hot=1';
				break;
			case 'is_new':
				$where .= ' AND is_new=1';
				break;
			case 'is_promote':
				$where .= " AND is_promote = 1 AND promote_price > 0 AND promote_start_date <= '$today' AND promote_end_date >= '$today'";
				break;
			case 'all_type';
			$where .= " AND (is_best=1 OR is_hot=1 OR is_new=1 OR (is_promote = 1 AND promote_price > 0 AND promote_start_date <= '" . $today . "' AND promote_end_date >= '" . $today . "'))";
		}

		/* 库存警告 */
		if ($filter['stock_warning'])
		{
			$where .= ' AND goods_number <= warn_number ';
		}

		/* 品牌 */
		if ($filter['brand_id'])
		{
			$where .= " AND brand_id='$filter[brand_id]'";
		}

		/* 扩展 */
		if ($filter['extension_code'])
		{
			$where .= " AND extension_code='$filter[extension_code]'";
		}

		/* 关键字 */
		if (!empty($filter['keyword']))
		{
			$where .= " AND (goods_sn LIKE '%" . mysql_like_quote($filter['keyword']) . "%' OR goods_name LIKE '%" . mysql_like_quote($filter['keyword']) . "%')";
		}

		if ($real_goods > -1)
		{
			$where .= " AND is_real='$real_goods'";
		}

		/* 上架 */
		if ($filter['is_on_sale'] !== '')
		{
			$where .= " AND (is_on_sale = '" . $filter['is_on_sale'] . "')";
		}

		/* 供货商 */
		if (!empty($filter['suppliers_id']))
		{
			$where .= " AND (suppliers_id = '" . $filter['suppliers_id'] . "')";
		}

		$where .= $conditions;

		/* 记录总数 */
		$sql = "SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table('goods'). " AS g WHERE is_delete='$is_delete' $where";
		$filter['record_count'] = $GLOBALS['db']->getOne($sql);

		/* 分页大小 */
		$filter = page_and_size($filter);

		$sql = "SELECT goods_id, goods_name, goods_type, goods_sn, shop_price, is_on_sale, is_best, is_new, is_hot, sort_order, goods_number, give_integral, " .
				" (promote_price > 0 AND promote_start_date <= '$today' AND promote_end_date >= '$today') AS is_promote ".
				" FROM " . $GLOBALS['ecs']->table('goods') . " AS g WHERE is_delete='$is_delete' $where" .
				" ORDER BY $filter[sort_by] $filter[sort_order] ".
				" LIMIT " . $filter['start'] . ",$filter[page_size]";

		$filter['keyword'] = stripslashes($filter['keyword']);
		set_filter($filter, $sql, $param_str);
	}
	else
	{
		$sql    = $result['sql'];
		$filter = $result['filter'];
	}
	$row = $GLOBALS['db']->getAll($sql);
	 
	foreach ($row as $key => $value)
	{
		$time="select max(goods_time) from " .$GLOBALS['ecs']->table('stock_change'). " where goods_id =".$value['goods_id']."";
		$time1=$GLOBALS['db'] -> getOne($time);
		$time1=date('Y-m-d H:i:s',$time1);
		$row[$key]['goods_time'] = $time1;
	}
	return array('goods' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}
?>


