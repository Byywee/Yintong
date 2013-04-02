<?php
//created in 2013-4-2
//by AnyLiu


define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
require_once(ROOT_PATH . '/' . ADMIN_PATH . '/includes/lib_goods.php');
include_once(ROOT_PATH . '/includes/cls_image.php');
$image = new cls_image($_CFG['bgcolor']);
$exc = new exchange($ecs->table('goods'), $db, 'goods_id', 'goods_name');



/*------------------------------------------------------ */
//--  库存管理
/*------------------------------------------------------ */

if($_REQUEST['act'] == 'stock_count')
{
	$ecs = $GLOBALS['ecs'];
	$db = $GLOBALS['db'];
	date_default_timezone_set('Asia/Shanghai');//设置时区为上海 
	$start_time = date('Y-m-d H:i',time()-24*3600);
	$end_time   = date('Y-m-d H:i',time());
	$where = " and goods_time >= ".(time()-24*3600)." and goods_time <= ".time();
	if($_REQUEST['search'] == 'search')
	{
		$start_time = $_POST['start_time'] == '' ? $start_time : $_POST['start_time'];
		$end_time = $_POST['start_time'] == '' ? $end_time : $_POST['end_time'];
		if(strtotime($start_time) < strtotime($end_time))
		{
			 $where = " and goods_time >= ".strtotime($start_time)." and goods_time <= ".strtotime($end_time);
		}
	}
	$sql_get_goods = " select goods_id,goods_name,goods_sn,goods_number from ".$ecs->table('goods');
	
	$goods_list = $db->getAll($sql_get_goods);
	
	$goods = get_stock_count();
	$smarty->assign('goods_list',   $goods['goods']);
    $smarty->assign('filter',       $goods['filter']);
    $smarty->assign('record_count', $goods['record_count']);
    $smarty->assign('page_count',   $goods['page_count']);
	$smarty->assign('time_yestoday',$start_time);
	$smarty->assign('time_today',$end_time);
	$smarty->assign('cat_list',     cat_list(0, $cat_id));
    $smarty->assign('brand_list',   get_brand_list());
    $smarty->assign('intro_list',   get_intro_list());
	$smarty->assign('ur_here', '库存日结');
	$smarty->assign('full_page',    1);
	$smarty->display("stock_count.htm");
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{

	$goods_list = get_stock_count();
	$smarty->assign('goods_list',   $goods_list['goods']);
	$smarty->assign('filter',       $goods_list['filter']);
	$smarty->assign('record_count', $goods_list['record_count']);
	make_json_result($smarty->fetch('stock_count.htm'), '',
		array('filter' => $goods_list['filter'], 'page_count' => $goods_list['page_count']));
		
}
//获取商品信息
function get_stock_count()
{
    /* 过滤条件 */
	$ecs = $GLOBALS['ecs'];
	$db = $GLOBALS['db'];
    $param_str = '-' . $is_delete . '-' . $real_goods;
    $result = get_filter($param_str);
    if ($result === false)
    {
        $day = getdate();
        $today = local_mktime(23, 59, 59, $day['mon'], $day['mday'], $day['year']);

        $filter['cat_id']           = empty($_REQUEST['cat_id']) ? 0 : intval($_REQUEST['cat_id']);
        $filter['intro_type']       = empty($_REQUEST['intro_type']) ? '' : trim($_REQUEST['intro_type']);
        $filter['brand_id']         = empty($_REQUEST['brand_id']) ? 0 : intval($_REQUEST['brand_id']);
        $filter['keyword']          = empty($_REQUEST['keyword']) ? '' : trim($_REQUEST['keyword']);
        if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keyword'] = json_str_iconv($filter['keyword']);
        }
        $filter['sort_by']          = empty($_REQUEST['sort_by']) ? 'goods_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order']       = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);
        $filter['extension_code']   = empty($_REQUEST['extension_code']) ? '' : trim($_REQUEST['extension_code']);
        $filter['start_time']   = empty($_REQUEST['start_time'])? '' : strtotime($_REQUEST['start_time']);
		$filter['end_time']   = empty($_REQUEST['end_time'])? '' : strtotime($_REQUEST['end_time']); 
		
		if($filter['start_time'] > $filter['end_time'])
		{
			$temp = $filter['start_time'];
			$filter['start_time'] = $filter['end_time'];
			$filter['end_time'] = $temp;
		}

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

        /* 品牌 */
        if ($filter['brand_id'])
        {
            $where .= " AND brand_id='$filter[brand_id]'";
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

        $where .= $conditions;

        /* 记录总数 */
        $sql = "SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table('goods'). " AS g WHERE is_delete='$is_delete' $where";
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);

        /* 分页大小 */
        $filter = page_and_size($filter);

        $sql = "SELECT goods_id, goods_name, goods_sn,goods_number" .
                    " FROM " . $GLOBALS['ecs']->table('goods') .
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
	
	foreach($row as $key=>$value)
	{
		$sql_get_stock_in     = " select sum(goods_stock_num) from ".$ecs->table('stock_change');
		$sql_get_stock_in    .= " where goods_id = ".$value['goods_id']." and goods_op_type = 1";
		
		$sql_get_stock_out    = " select sum(goods_stock_num) from ".$ecs->table('stock_change');
		$sql_get_stock_out   .= " where goods_id = ".$value['goods_id']." and goods_op_type = 2";
		
		$sql_get_stock_loss   = " select sum(goods_stock_num) from ".$ecs->table('stock_change');
		$sql_get_stock_loss  .= " where goods_id = ".$value['goods_id']." and goods_op_type = 3";
		
		if( $filter['start_time']!= 0 && $filter['start_time'] != 0)
		{
			$sql_get_stock_out   .= " and goods_time >= ". $filter['start_time']." and goods_time <= ".$filter['start_time']."";
			$sql_get_stock_in    .= " and goods_time >= ". $filter['start_time']." and goods_time <= ".$filter['start_time']."";
			$sql_get_stock_loss  .= " and goods_time >= ". $filter['start_time']." and goods_time <= ".$filter['start_time']."";
		}
		$row[$key]['in']   = $db->getOne($sql_get_stock_in);
		$row[$key]['out']  = $db->getOne($sql_get_stock_out);
		$row[$key]['loss'] = $db->getOne($sql_get_stock_loss);
	}
    return array('goods' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

}
function list_link($is_add = true, $extension_code = '')
{
    $href = 'goods.php?act=list';
    if (!empty($extension_code))
    {
        $href .= '&extension_code=' . $extension_code;
    }
    if (!$is_add)
    {
        $href .= '&' . list_link_postfix();
    }

    if ($extension_code == 'virtual_card')
    {
        $text = $GLOBALS['_LANG']['50_virtual_card_list'];
    }
    else
    {
        $text = $GLOBALS['_LANG']['01_goods_list'];
    }

    return array('href' => $href, 'text' => $text);
}

//获取所有商品的库存数量



/**
 * 添加链接
 * @param   string  $extension_code 虚拟商品扩展代码，实体商品为空
 * @return  array('href' => $href, 'text' => $text)
 */
function add_link($extension_code = '')
{
    $href = 'goods.php?act=add';
    if (!empty($extension_code))
    {
        $href .= '&extension_code=' . $extension_code;
    }

    if ($extension_code == 'virtual_card')
    {
        $text = $GLOBALS['_LANG']['51_virtual_card_add'];
    }
    else
    {
        $text = $GLOBALS['_LANG']['02_goods_add'];
    }

    return array('href' => $href, 'text' => $text);
}

/**
 * 检查图片网址是否合法
 *
 * @param string $url 网址
 *
 * @return boolean
 */
function goods_parse_url($url)
{
    $parse_url = @parse_url($url);
    return (!empty($parse_url['scheme']) && !empty($parse_url['host']));
}

/**
 * 保存某商品的优惠价格
 * @param   int     $goods_id    商品编号
 * @param   array   $number_list 优惠数量列表
 * @param   array   $price_list  价格列表
 * @return  void
 */
function handle_volume_price($goods_id, $number_list, $price_list)
{
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('volume_price') .
           " WHERE price_type = '1' AND goods_id = '$goods_id'";
    $GLOBALS['db']->query($sql);


    /* 循环处理每个优惠价格 */
    foreach ($price_list AS $key => $price)
    {
        /* 价格对应的数量上下限 */
        $volume_number = $number_list[$key];

        if (!empty($price))
        {
            $sql = "INSERT INTO " . $GLOBALS['ecs']->table('volume_price') .
                   " (price_type, goods_id, volume_number, volume_price) " .
                   "VALUES ('1', '$goods_id', '$volume_number', '$price')";
            $GLOBALS['db']->query($sql);
        }
    }
}

/**
 * 修改商品库存
 * @param   string  $goods_id   商品编号，可以为多个，用 ',' 隔开
 * @param   string  $value      字段值
 * @return  bool
 */
function update_goods_stock($goods_id, $value)
{
    if ($goods_id)
    {
        /* $res = $goods_number - $old_product_number + $product_number; */
        $sql = "UPDATE " . $GLOBALS['ecs']->table('goods') . "
                SET goods_number = goods_number + $value,
                    last_update = '". gmtime() ."'
                WHERE goods_id = '$goods_id'";
        $result = $GLOBALS['db']->query($sql);

        /* 清除缓存 */
        clear_cache_files();

        return $result;
    }
    else
    {
        return false;
    }
}
?>
