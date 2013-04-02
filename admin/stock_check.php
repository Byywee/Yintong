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
if($_REQUEST['act'] == 'stock_check')
{

	//批量盘点操作
	if(isset($_POST['btnSubmit']))
	{
	
		$goods_id = $_POST['checkboxes'];
		$stock_loss_num_up = array();
		foreach($_POST['checkboxes'] as $key=>$value_num)
		{
			$stock_loss_num_up[] = ($_POST['stock_loss_num_'.$_POST['checkboxes'][$key]]);
		}
		$date = time();
		foreach($goods_id as $key=>$good_id)
		{
			
			$sql_update_num = " update ".$ecs->table('goods')." set goods_number = goods_number - ".$stock_loss_num_up[$key]." where goods_id = ".$good_id."";
			if($db->query($sql_update_num))
			{
				$sql_insert_stock_change  = " insert into ".$ecs->table('stock_change');
				$sql_insert_stock_change .= " (goods_id,goods_stock_num,goods_time,goods_op_type,goods_op_user_name)";
				$sql_insert_stock_change .= " values('".$good_id."',".$stock_loss_num_up[$key].",".$date.",3,'".$_SESSION['admin_name']."')";
			
				if($stock_loss_num_up[$key] != 0)
				{
					$db->query($sql_insert_stock_change);
					echo "<script>alert('数据更新成功')</script>";
				}
			}
			else
			{
				echo "<script>alert('数据更新失败')</script>";
			}
		}
	}
	
	//盘点单个商品
	if($_REQUEST['one'] == 'one')
	{
		//print_r($_REQUEST);
		$goods_id = $_REQUEST['goods_id'];
		$stock_loss_num = $_REQUEST['stock_loss_num'];
		$date = time();
		if($stock_loss_num != 0 && $goods_id != 0)
		{
			$sql_one_confirm = " update ".$ecs->table('goods')." set goods_number = goods_number - ".$stock_loss_num." where goods_id = ".$goods_id."";
			if($db->query($sql_one_confirm))
			{
				//print_r($_SESSION);
				$sql_insert_stock_change  = " insert into ".$ecs->table('stock_change');
				$sql_insert_stock_change .= " (goods_id,goods_stock_num,goods_time,goods_op_type,goods_op_user_name)";
				$sql_insert_stock_change .= " values('".$goods_id."',".$stock_loss_num.",".$date.",3,'".$_SESSION['admin_name']."')";
				//print($sql_insert_stock_change);
				if($stock_loss_num != 0)
				{
					$db->query($sql_insert_stock_change);
				}
				echo "<script>alert('数据更新成功')</script>";
			}
			else
			{
				echo "<script>alert('数据更新失败')</script>";
			}		
		}
	}
	$url = ($_REQUEST['print'] == 'print') ? 'stock_check_print.html':'stock_check.htm';
	/*$goods['filter'] = $filter;
	$sql_get_goods_count = " select count(*) from ".$ecs->table('goods');*/
	//$goods['num'] = $db->getOne($sql_get_goods_count);
	//$goods['list'] = $db->getAll($sql);
	//$goods['page_count'] = (($goods['num']/$filter['page_size']) > (int)($goods['num']/$filter['page_size'])) ? ((int)($goods['num']/$filter['page_size']+1)) : ((int)($goods['num']/$filter['page_size']));
	
	$goods = get_stock_check();
    $smarty->assign('ur_here', '库存盘点');
	$smarty->assign('goods_list',   $goods['goods']);
    $smarty->assign('filter',       $goods['filter']);
    $smarty->assign('record_count', $goods['record_count']);
    $smarty->assign('page_count',   $goods['page_count']);
	$smarty->assign('cat_list',     cat_list(0, $cat_id));
    $smarty->assign('brand_list',   get_brand_list());
    $smarty->assign('intro_list',   get_intro_list());
	$smarty->assign('lang',         $_LANG);
	$smarty->assign('full_page',    1);
	$smarty->display($url);
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
		$hint = $_REQUEST['hint'];
			$goods_list = get_stock_check();
			$smarty->assign('goods_list',   $goods_list['goods']);
			$smarty->assign('filter',       $goods_list['filter']);
			$smarty->assign('record_count', $goods_list['record_count']);
			$smarty->assign('page_count',   $goods_list['page_count']);
		
			make_json_result($smarty->fetch('stock_check.htm'), '',
				array('filter' => $goods_list['filter'], 'page_count' => $goods_list['page_count']));
}
//获取商品信息
function get_stock_check()
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
        $filter['brand_id']         = empty($_REQUEST['brand_id']) ? 0 : intval($_REQUEST['brand_id']);
        $filter['keyword']          = empty($_REQUEST['keyword']) ? '' : trim($_REQUEST['keyword']);
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

        $sql = "SELECT goods_id, goods_name, goods_type, goods_sn, shop_price, is_on_sale, is_best, is_new, is_hot, sort_order, goods_number, integral, " .
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
//进货
function get_stock()
{
	$sql_get_goods_stock_num  = " select goods_id,goods_name,goods_sn,shop_price,goods_number from ".$GLOBALS['ecs']->table('goods');
	$goods_stock_num = $GLOBALS['db']->getAll($sql_get_goods_stock_num);
	return $goods_stock_num;
}
//退货
function get_return_stock()
{
	$sql_get_goods_stock_num  = " select goods_id,goods_name,goods_sn,shop_price,goods_number from ".$GLOBALS['ecs']->table('goods');
	$goods_stock_num = $GLOBALS['db']->getAll($sql_get_goods_stock_num);
	return $goods_stock_num;
}

//盘点
function get_stock_count()
{
	$result = get_filter();
	if($result === false)
	{
		$filter['goods_id'] = empty($_REQUEST['goods_id']) ? '' : $_REQUEST['goods_id'];
		
		$filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

		if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0)
		{
			$filter['page_size'] = intval($_REQUEST['page_size']);
		}
		elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0)
		{
			$filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
		}
		else
		{
			$filter['page_size'] = 51;
		}
	}
    else
	{
		$sql    = $result['sql'];
        $filter = $result['filter'];
	}
	
	$sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('goods');
	$filter['record_count']   = $GLOBALS['db']->getOne($sql);
	$filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

	/* 查询 */
	$sql = "SELECT goods_id,goods_name,goods_sn,goods_number from ".$GLOBALS['ecs']->table('goods');

	foreach (array('goods_sn', 'goods_name', 'goods_id', 'goods_number') AS $val)
	{
		$filter[$val] = stripslashes($filter[$val]);
	}
	set_filter($filter, $sql);

	$goods_stock_num = $GLOBALS['db']->getAll($sql);

	
	$goods = array('goods' => $goods_stock_num,'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
	/* print_r($goods); */
	return $goods;
}


//日结

//进销存报表



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

function get_goodslist()
{
    $result = get_filter();
    if ($result === false)
    {
        /* 分页大小 */
        $filter = array();

        /* 记录总数以及页数 */
        if (isset($_POST['keyword']))
        {
            $sql = "SELECT COUNT(*) FROM ".$GLOBALS['ecs']->table('goods') ." WHERE goods_name = '".$_POST['keyword']."'";
        }
        else
        {
            $sql = "SELECT COUNT(*) FROM ".$GLOBALS['ecs']->table('goods');
        }

        $filter['record_count'] = $GLOBALS['db']->getOne($sql);

        $filter = page_and_size($filter);

        /* 查询记录 */
        if (isset($_POST['keyword']))
        {
            if(strtoupper(EC_CHARSET) == 'GBK')
            {
                $keyword = iconv("UTF-8", "gb2312", $_POST['keyowrd']);
            }
            else
            {
                $keyword = $_POST['keyword'];
            }
            $sql = "SELECT * FROM ".$GLOBALS['ecs']->table('goods')." WHERE goods_name like '%{$keyword}%'";
        }
        else
        {
            $sql = "SELECT * FROM ".$GLOBALS['ecs']->table('goods')." ";
        }

        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }
    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    $arr = array();
    while ($rows = $GLOBALS['db']->fetchRow($res))
    {
        /*$brand_logo = empty($rows['brand_logo']) ? '' :
            '<a href="../' . DATA_DIR . '/brandlogo/'.$rows['brand_logo'].'" target="_brank"><img src="images/picflag.gif" width="16" height="16" border="0" alt='.$GLOBALS['_LANG']['brand_logo'].' /></a>';
        $site_url   = empty($rows['site_url']) ? 'N/A' : '<a href="'.$rows['site_url'].'" target="_brank">'.$rows['site_url'].'</a>';

        $rows['brand_logo'] = $brand_logo;
        $rows['site_url']   = $site_url;*/

        $arr[] = $rows;
    }

    return array('goods' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}


?>
