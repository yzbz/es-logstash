<?php
//处理搜索功能
class Search
{
    const IS_DELETE_NO = 0;
   
    public function esHandle() {
        //排序 此种方式置顶
        $sort = [];
        $sort_conf_key = ['total', 'authentica', 'price', 'nearest_days'];
        $sort_conf = [
            'total' => [ //综合排序
                'all',//综合排序
                'popularity',//人气最高
                'newest',//最新发布
            ],
            'authentica' => [
                'all', //全部
                'certified',//已认证用户
                'optimization',//已认证用户
            ],
            'price' => [
                'asc', //升序
                'desc',//降序
            ],
            'nearest_days' => [
                '3',
                '7',
                '30',
            ],
        ];
        //筛选
        $where = [];
        $where['should'] = [];
        $where['must'] = [];
        $where['filter'] = [];
        //排序配置
        if(!empty($_REQUEST['sort'])) {
            if (count(explode(',', $_REQUEST['sort'])) == 2) {
                list($sortKey, $sortVal) = explode(',', $_REQUEST['sort']);
                if (in_array($sortKey, $sort_conf_key) && in_array($sortVal, $sort_conf[$sortKey])) {
                    //排序字段存在情况再去处理,并且为all的情况则不去处理
                    if ($sortVal != 'all') {
                        switch ($sortKey) {
                            case 'total':
                                if ($sortVal == 'popularity') {
                                    $sort['popularity_cnt'] = 'desc';
                                } else {
                                    $sort['create_t'] = 'desc';
                                }
                                break;
                            case 'price':
								$sort['price'] = $sortVal;
                                break;
                            case 'authentica':
//                                $sort = ['certified' => 'desc']; //已认证标记1
                                array_push($where['filter'], ['term' => ['status' => true]]); //只查询已认证的用户
                                break;
                        }
                    }
                }
            }
        }
        $sort['_script'] = [
            "script" => [
                "lang" => "painless",
                'source' => "if('{$_REQUEST['name']}'.equals(doc['wood_name'].value)){10}else{Math.floor(_score / 50)}",
            ],
            "type" => "number",
            "order" => "desc",
        ]; //筛选排序第一位，其次默认

        $search = $_REQUEST['name']; //搜索栏的查询条件
        //木材分类 原木板材
        if (!empty($_REQUEST['className'])) {
            array_push($where['filter'], ['term' => ['type_id' => $_REQUEST['className']]]);
        } else if (!empty($search)) {
            $typeId = self::getTypeIdByName($search);
            if($typeId) array_push($where['should'], [ 'term' => ['type_id' => $typeId]]);
        }
        //木材种类 搜索名称
        if (!empty($_REQUEST['woodName'])) {
            $woodName = $_REQUEST['woodName'];
            $where['filter'][] = ['match' => ['wood_name' => $woodName]];
        } else if (!empty($search)) {
            $where['should'][] = [ 'multi_match' => ['boost' => 10,'query' => $search,'fields' => ['wood_name.pinyin', 'wood_name']]];
        }

        //木材产地 搜索产地
        if (!empty($_REQUEST['woodArea'])) {
            $woodArea = $_REQUEST['woodArea'];
            if ($woodArea == '其它') {
                $where['must_not'][] = ['term' => ['wood_area' => '北京']];
                $where['must_not'][] = ['term' => ['wood_area' => '天津']];
                $where['must_not'][] = ['term' => ['wood_area' => '上海']];
                $where['must_not'][] = ['term' => ['wood_area' => '福建']];
                $where['must_not'][] = ['term' => ['wood_area' => '重庆']];
                $where['must_not'][] = ['term' => ['wood_area' => '河北']];
                $where['must_not'][] = ['term' => ['wood_area' => '山西']];
                $where['must_not'][] = ['term' => ['wood_area' => '吉林']];
                $where['must_not'][] = ['term' => ['wood_area' => '黑龙江']];
                $where['must_not'][] = ['term' => ['wood_area' => '江苏']];
                $where['must_not'][] = ['term' => ['wood_area' => '浙江']];
             
            }
            else if ($woodArea == '国产') {
                $where['must_not'][] = ['term' => ['wood_area' => '其它']];
                $where['must_not'][] = ['term' => ['wood_area' => '缅甸']];
                $where['must_not'][] = ['term' => ['wood_area' => '美国']];
                $where['must_not'][] = ['term' => ['wood_area' => '南美']];
                $where['must_not'][] = ['term' => ['wood_area' => '北美']];
                $where['must_not'][] = ['term' => ['wood_area' => '俄罗斯']];
                $where['must_not'][] = ['term' => ['wood_area' => '非洲']];
                $where['must_not'][] = ['term' => ['wood_area' => '欧洲']];
                $where['must_not'][] = ['term' => ['wood_area' => '海外']];
            }
            else {
                $where['filter'][] = ['term' => ['wood_area' => $_REQUEST['woodArea']]];
            }
        }
        //木材交易地 搜索交易地
        if (!empty($_REQUEST['sellArea'])) {

            array_push($where['filter'], [ 'term' => ['sell_area' => $_REQUEST['sellArea']]]);
        } else if (!empty($search)) {
            array_push($where['should'], [ 'multi_match' => ['query' => $search, 'fields' => ['sell_area.pinyin', 'sell_area']]]);
        }
        //根据描述搜索
        if (!empty($search)) {
          
			array_push($where['should'], [ 'multi_match' => ['query' => $search, 'fields' => ['desc.pinyin', 'desc']]]);
          
        }
        $add = self::addLog();
        return ['where' => $where, 'sort' => $sort, 'add_id' => $add];
    }

    public function complexWhereOr($whereOr1, $whereOr2, $whereOr3, $whereOr4,$whereOr5) {
        $whereOr = [];
        if (!empty($whereOr1)) {
            $whereOr2['_logic'] = 'or';
            $whereOr2['_complex'] = $whereOr1;
        }
        if (!empty($whereOr2)) {
            $whereOr3['_logic'] = 'or';
            $whereOr3['_complex'] = $whereOr2;
        }else if (!empty($whereOr1)) {
            $whereOr3['_logic'] = 'or';
            $whereOr3['_complex'] = $whereOr1;
        }
        if (!empty($whereOr3)) {
            $whereOr4['_logic'] = 'or';
            $whereOr4['_complex'] = $whereOr3;
        }else if (!empty($whereOr2)) {
            $whereOr4['_logic'] = 'or';
            $whereOr4['_complex'] = $whereOr2;
        }else if (!empty($whereOr1)) {
            $whereOr4['_logic'] = 'or';
            $whereOr4['_complex'] = $whereOr1;
        }
        if (!empty($whereOr4)) {
            $whereOr5['_logic'] = 'or';
            $whereOr5['_complex'] = $whereOr4;
        }else if (!empty($whereOr3)) {
            $whereOr5['_logic'] = 'or';
            $whereOr5['_complex'] = $whereOr3;
        }else if (!empty($whereOr2)) {
            $whereOr5['_logic'] = 'or';
            $whereOr5['_complex'] = $whereOr2;
        }else if (!empty($whereOr1)) {
            $whereOr5['_logic'] = 'or';
            $whereOr5['_complex'] = $whereOr1;
        }
        if (!empty($whereOr5)) {
            $whereOr['_logic'] = 'or';
            $whereOr['_complex'] = $whereOr5;
        }else if (!empty($whereOr4)) {
            $whereOr['_logic'] = 'or';
            $whereOr['_complex'] = $whereOr4;
        }else if (!empty($whereOr3)) {
            $whereOr['_logic'] = 'or';
            $whereOr['_complex'] = $whereOr3;
        }else if (!empty($whereOr2)) {
            $whereOr['_logic'] = 'or';
            $whereOr['_complex'] = $whereOr2;
        }else if (!empty($whereOr1)) {
            $whereOr['_logic'] = 'or';
            $whereOr['_complex'] = $whereOr1;
        }
        return $whereOr;
    }

   
}
