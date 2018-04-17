<?php
namespace wstree;
class Tree implements \ArrayAccess{
    public $root=null;
    public $current=null;
    /**
     * 插入$root下节点 通常是初始化树
     * 插入前应先进行排序 按从上到下、左到右的方式排序 参考 Format->preSort()
     * @param  array   $data 用户插入数据
     * @param  integer $pid  起始pid
     * @param  boolean  $sort   是否先进行按主键值的排序
     * @param  integer  $level  级别默认为0 不建议传递
     * @param  string   $pidKey  父级ID 键名
     * @return Tree||null 返回自身或者null
     * $options=[$pid=0,$sort=true,$level=0,$pidKey='pid'];
     */
     function init($data,$options=[]){
         if(empty($data)){
             return;
         }
         $list=[];
         $this->preSort($data,$list,$options);
         $list=$this->_childrens($list);
         $rs=null;
         foreach ($list as $k => $v) {
             if(!$rs){
                 $rs=$this->tree($v);
             }else{
                 if($v['_childrens']){
                     $rs->tree($v);
                 }else{
                     $rs->leaf($v);
                 }
                 $nextKey=$k+1;
                 if(!isset($list[$nextKey])||$list[$nextKey]['_level']<$v['_level']){
                     $rs->end();
                 }

             }
         }
         return $rs;
     }
    /**
     * 插入新节点(公排|弱区)
     * @param  mixed $key  插入的值
     * @param  Node $node  节点的实例
     * @param  boolean $natural 默认：false 是否自然排序(公排 从上到下 左到右) 默认为平衡排列(优选弱区)
     * @return Node
     */
    function insert($key,$node=null,$natural=false){
        $newNode=new Node($key);
        if(is_null($node)){
            $node=$this->root;
        }
        $rs=$this->insertable($node,$natural);
        if(!$rs){
            $this->root=$newNode;
        }else{
            if(is_null($rs->left)){
                $rs->left=$newNode;
            }else{
                $rs->right=$newNode;
            }
            $newNode->parent=$rs;
        }
        return $newNode;
    }
    /**
     * 查询可插入的节点信息(公排|弱区)
     * 通常作为插入用户时获取新用户的父节点
     * @param  Node $node 节点的实例
     * @param  boolean $natural 默认：false 是否自然排序(公排 从上到下 左到右) 默认为平衡排列(优选弱区)
     * @return array      [node,position]
     */
    function insertable($node,$natural=false,$rootNode=null){
        if(is_null($node)&&is_null($this->root)){
            return;
        }
        if(is_null($rootNode)){ // 首次会执行该条件
            $rootNode=$node;
        }
        $l_height=0;
        $r_height=0;
        if($natural){
            $l_height=$this->getMinHeight($node['left']);
            $r_height=$this->getMinHeight($node['right']);
        }else{
            $l_height=$this->getHeight($node['left']);
            $r_height=$this->getHeight($node['right']);
        }

        if($r_height<$l_height){
            if(is_null($node['right'])){
                return $node;
            }
            return $this->insertable($node['right'],$natural,$rootNode);
        }else{
            if(is_null($node['left'])){
                if(!$natural){
                    $is_full=$this->isFull($rootNode);
                    $root_l_height=$this->getHeight($rootNode['left']);
                    $root_r_height=$this->getHeight($rootNode['right']);
                    if($root_l_height===$root_r_height&&!$is_full){
                        $parent=null;
                        $l_is_full=$this->isFull($rootNode['left']);
                        if($l_is_full){ // 加入右边
                            $isComplete=$this->isComplete($rootNode['right'],function($item)use(&$parent){
                                if(!is_null($item)){
                                    $parent=$item;
                                }
                            });
                        }else{ // 加入左边
                            $isComplete=$this->isComplete($rootNode['left'],function($item)use(&$parent){
                                if(!is_null($item)){
                                    $parent=$item;
                                }
                            });
                        }
                        if(!is_null($parent)&&$isComplete){
                            return $parent;
                        }
                     }
                 }

                return $node;
            }

            return $this->insertable($node['left'],$natural,$rootNode);
        }
    }
    /**
     * 是否满二叉树
     * @param  Node  $node 当前节点
     * @param  boolean  $getNode 当前节点
     * @return boolean
     */
    function isFull($node=null,$getNode=false){
        if(is_null($node)){
            $node=$this->root;
        }
        $length=0;
        $parent=null;
        $this->preOrder($node,function($item)use(&$length,&$parent,$getNode){
            $length++;
            if($getNode&&is_null($item->left)){
                $parent=$item->parent;
            }
        });
        $height=$this->getHeight($node);
        $isFull=$length===pow(2,$height)-1;
        if($getNode){
            return $isFull?null:$parent;
        }
        return $isFull;
    }
    /**
     * 是否完全二叉树
     * @param  Node  $node 当前节点
     * @param  boolean  $getNode 当前节点
     * @param  function $callback 一个回调函数 注入参数 在insertable()中使用
     * @return boolean
     */
    function isComplete($node=null,$callback=null){
        $breakPoint=false;
        $isComplete=true;
        $breakNode=null;
        $this->levelOrder($node,function($item)use(&$breakPoint,&$isComplete,&$breakNode){
            if(is_null($item)&&!$breakPoint){
                $breakPoint=true;
            }
            if(!$breakPoint){
                $parent=$item->parent;
                if(!isset($parent['right'])||is_null($parent['right'])){
                    $breakNode=$parent;
                }else{
                    $parent=$parent->parent;
                    if(is_null($parent)){
                        $breakNode=$item;
                    }elseif(!is_null($parent->right)){
                        $breakNode=$parent->right;
                    }
                }
            }
            if($breakPoint&&!is_null($item)){
                $isComplete=false;
            }
        });
        if(is_callable($callback)){
            call_user_func($callback,$breakNode);
        }
        //echo "<br>breakNode:".$breakNode->value;
        return $isComplete;
    }
    /**
     * 获取当前节点深度
     * @param  Node $node 节点的实例
     * @return integer
     */
    function getDepth($node){
        if(is_null($node)){
            return 0;
        }
        return $this->getDepth($node['parent'])+1;
    }
    /**
     * 获取当前节点高度
     * @param  Node $node 节点的实例
     * @return integer
     */
    function getHeight($node){
        if(is_null($node)){
            return 0;
        }
        return max($this->getHeight($node['left']),$this->getHeight($node['right']))+1;

    }
    /**
     * 获取当前节点高度(最短的那一边)
     * @param  Node $node 节点的实例
     * @return integer
     */
    function getMinHeight($node){
        if(is_null($node)){
            return 0;
        }
        return min($this->getMinHeight($node['left']),$this->getMinHeight($node['right']))+1;
    }
    /**
     * 设置一个节点
     * @param  mixed $key  需要设置的值
     * @return Tree 返回current指向$key生成的节点（当前节点）的当前类
     */
    function tree($key){
        $node=new Node($key);
        if(!is_null($this->root)&&!is_null($this->current)){
            $this->_addNode($node);
            $this->current=$node;
            return $this;
        }
        if(is_null($this->root)){
            $this->root=$node;
        }
        if(is_null($this->current)){
            $this->current=$node;
        }
        return $this;
    }
    /**
     * 设置一个叶节点(如果这是第一个则与tree()作用相同)
     * @param  mixed $key  需要设置的值
     * @return Tree 返回current指向当前节点父级的当前类
     */
    function leaf($key){
        if(is_null($this->root)||is_null($this->current)){
            return $this->tree($key);
        }
        $node=new Node($key);
        $this->_addNode($node);
        return $this;
    }
    /**
     * 返回指向上级节点(根节点返回的依然是根节点)的当前类
     * @return Tree 返回current指向父级的父级类
     */
    function end(){
        $current=$this->current;
        if(!is_null($current->parent)){
            $this->current=$current->parent;
        }
        return $this;
    }
    /**
     * 增加一个节点(tree()、leaf()的助手方法)
     * @param Node $node 节点的实例
     */
    private function _addNode($node){
        $current=$this->current;
        if(is_null($current['left'])){
            $this->current->left=$node;
        }else{
            $this->current->right=$node;
        }
        $node['parent']=$this->current;
    }


    /**
     * 上下左右遍历(层层遍历)
     * @param  callable $callback 一个回调函数 接收一个Node实例参数
     * （这里的参数与另三个遍历参数有区别 主要为了应用于$this->isComplete()方法中）
     * @param  Node $node       初始节点的实例
     * @return void
     */
    function levelOrder($node,$callback){
        $queue=[$node];
        call_user_func($callback,$node);
        $parent=array_shift($queue);
        while(!is_null($parent)) {
            $left=$parent->left;
            $right=$parent->right;
            call_user_func($callback,$left);
            call_user_func($callback,$right);
            if(!is_null($left)&&!is_null($right)){
                array_push($queue,$left,$right);
            }elseif(!is_null($right)){
                array_push($queue,$left);
            }
            $parent=array_shift($queue);
        }
    }
    /**
     * 先序遍历
     * @param  Node $node       初始节点的实例
     * @param  callable $callback 一个回调函数 接收一个Node实例参数
     * @return void
     */
    function preOrder($node,$callback){
        if(!is_null($node)){
            call_user_func($callback,$node);
            $this->preOrder($node['left'],$callback);
            $this->preOrder($node['right'],$callback);
        }
    }
    /**
     * 中序遍历
     * @param  Node $node       初始节点的实例
     * @param  callable $callback 一个回调函数 接收一个Node实例参数
     * @return void
     */
    function inOrder($node,$callback){
        if(!is_null($node)){
            $this->inOrder($node['left'],$callback);
            call_user_func($callback,$node);
            $this->inOrder($node['right'],$callback);
        }
    }
    /**
     * 后序遍历
     * @param  Node $node       初始节点的实例
     * @param  callable $callback 一个回调函数 接收一个Node实例参数
     * @return void
     */
    function postOrder($node,$callback){
        if(!is_null($node)){
            $this->postOrder($node['left'],$callback);
            $this->postOrder($node['right'],$callback);
            call_user_func($callback,$node);
        }
    }
    /**
     * 从上到下 从左到右的排序(层层排序)
     * @param  array   $data 用户数据
     * @param  integer $col  列数（二叉树、三叉树、四叉树。。。）
     * @param  integer $pid  起始pid
     * @param  string  $pidKey  父级ID 键名
     * @return array
     */
    static function levelSort($data,$pid=0,$pidKey='pid'){
        $arr=[];
        $ids=[$pid];
        while(!empty($ids)){
            $pid=array_shift($ids);
            $rs=array_filter($data,function($item) use($pid,$pidKey){
                return $pid===$item[$pidKey]?true:false;
            });
            if(count($rs)>1){
                usort($rs,function($a,$b) use(&$ids){
                    if($a['id']>$b['id']){
                        array_push($ids,$b['id'],$a['id']);
                        return 1;
                    }
                    array_push($ids,$a['id'],$b['id']);
                    return -1;
                });
            }elseif(!empty($rs)){
                array_push($ids,end($rs)['id']);
            }
            $arr=array_merge($arr,$rs);
        }
        return $arr;
    }
    /**
     * 先序 排序 以及是否存在子集判断
     * @param  array   $array 用户数据
     * @param  array   $data 引用类型最终返回的数据
     * @param  integer $pid  起始pid
     * @param  boolean  $sort   是否先进行按主键值的排序
     * @param  integer  $level  级别默认为0 不建议传递
     * @param  string   $pidKey  父级ID 键名
     * @return array
     * $options=[$pid=0,$sort=true,$level=0,$pidKey='pid'];
     */
    static function preSort($array,&$data,$options=[],$isFirst=true){
        if($isFirst){
            $pidKey=isset($options['pidKey'])?$options['pidKey']:'pid';
            $options=array_merge([$pidKey=>0,'sort'=>true,'level'=>0,'pidKey'=>'pid'],$options);
        }
        extract($options);
        if($sort){
            // 排序 待进行
            usort($array,function($a,$b){
                return $a['id']>$b['id']?1:-1;
            });
            $options['sort']=false;
        }
        //$nbsp='&nbsp;';
    	foreach($array as $v){
    		if($v[$pidKey]==$pid){
                $v['_level']=$level;
    			//$v['_prefix']=str_pad('',$level*strlen($nbsp)*8,$nbsp);
    			$data[]=$v;
                $options[$pidKey]=$v['id'];
                $options['level']++;
    			self::preSort($array,$data,$options,false);
    		}
    	}
    }
    /**
     * 子集个数统计（直属下级）
     * @param  array   $arr     统计数据
     * @param  string  $pidKey  父级ID 键名
     * @return array
     */
    static function _childrens($arr,$pidKey='pid'){
        foreach ($arr as $k => $v) {
            $_childrens=0;
            $id=$v['id'];
            $_childrens=array_filter($arr,function($item)use($id,$pidKey){
                return $item[$pidKey]===$id?true:false;
            });
            // 拥有子节点数量(直接下级数)
            $v['_childrens']=count($_childrens);
            $arr[$k]=$v;
        }
        return $arr;
    }

    // 私有化扩展 待定
    function setValue($key,$value){
        $this->$key=$value;
    }
    function offsetExists($key){
        return isset($this->$key);
    }
    function offsetGet($key){
        return $this->$key;
    }
    function offsetSet($key, $value){
        $this->$key=$value;
    }
    function offsetUnset($key){
        //unset($this->$key);
    }
}
