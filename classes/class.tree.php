<?php
/**
* Tree class
* data representation in hierachical trees using the Nested Set Model by Joe Celco
*
* @author Sascha Hofmann <shofmann@databay.de>
* @author Stefan Meyer <smeyer@databay.de>
* @version $Id$
*
* @package ilias-core
*/
class Tree
{
	/**
	* ilias object
	* @var		object	ilias
	* @access	private
	*/
	var $ilias;

	/**
	* points to actual position in tree (node)
	* @var		integer
	* @access	public
	*/
	var $node_id;

	/**
	* parent of current node. This information is needed for multi-refering the same child in the tree
	* @var		integer
	* @access	public
	*/
	var $parent_id;

	/**
	* points to root node (may be a subtree)
	* @var		integer
	* @access	public
	*/
	var $root_id;

	/**
	* to use different trees in one db-table
	* @var		integer
	* @access	public
	*/
	var $tree_id;

	/**
	* contains the path from root to current node (node_id)
	* @var		array
	* @access	public
	*/
	var $Path;
	
	/**
	* contains all subnodes of node (node_id)
	* @var		array
	* @access	public
	*/
	var $Childs;

	/**
	* contains leaf nodes of tree
	* @var		array
	* @access	public
	*/
	var $Leafs;

	/**
	* table name of tree table
	* @var		string
	* @access	private
	*/
	var $table_tree;

	/**
	* table name of object_data table
	* @var		string
	* @access	private
	*/
	var $table_obj_data;

	/**
	* table name of object_reference table
	* @var		string
	* @access	private
	*/
	var $table_obj_reference;

	/**
	* column name containing primary key in reference table
	* @var		string
	* @access	private
	*/
	var $ref_pk;

	/**
	* column name containing primary key in object table
	* @var		string
	* @access	private
	*/
	var $obj_pk;

	/**
	* Constructor
	* @access	public
	* @param	integer	$a_node_id		node_id
	* @param	integer	$a_root_id		root_id (optional)
	* @param	integer	$a_tree_id		tree_id (optional)
	*/
	function Tree($a_node_id, $a_root_id = 0, $a_tree_id = 0)
	{
		global $ilias;

		// set ilias
		$this->ilias =& $ilias;

		//init variables
		if (empty($a_root_id))
		{
			$a_root_id = ROOT_FOLDER_ID;
		}

		//init variables
		if (empty($a_tree_id))
		{
			$a_tree_id = ROOT_FOLDER_ID;
		}

		$this->node_id		  = $a_node_id;
		$this->root_id		  = $a_root_id;		
		$this->tree_id		  = $a_tree_id;
		$this->table_tree     = 'tree';
		$this->table_obj_data = 'object_data';
		$this->table_obj_reference = 'object_reference';
		$this->ref_pk = 'ref_id';
		$this->obj_pk = 'obj_id';
	}

	/**
	* set table names
	* The primary key of the table containing your object_data must be 'obj_id'
	* You may use a reference table.
	* If no reference table is specified the given tree table is directly joined
	* with the given object_data table.
	* The primary key in object_data table and its foreign key in reference table must have the same name!
	* 
	* @param	string	table name of tree table
	* @param	string	table name of object_data table
	* @param	string	table name of object_reference table (optional)
	* @access	public
	* @return	boolean
	*/
	function setTableNames($a_table_tree,$a_table_obj_data,$a_table_obj_reference = "")
	{
		$this->table_tree = $a_table_tree;
		$this->table_obj_data = $a_table_obj_data;
		$this->table_obj_reference = $a_table_obj_reference;

		return true;
	}

	/**
	* set column containing primary key in reference table
	* @access	public
	* @param	string	column name
	* @return	boolean	true, when successfully set
	*/
	function setReferenceTablePK($a_column_name)
	{
		if ($a_column_name)
		{
			$this->ref_pk = $a_column_name;
			return true;
		}
		
		// ref_pk wasn't set and holds default value specified in constructor
		return false;
	}

	/**
	* set column containing primary key in object table
	* @access	public
	* @param	string	column name
	* @return	boolean	true, when successfully set
	*/
	function setObjectTablePK($a_column_name)
	{
		if ($a_column_name)
		{
			$this->obj_pk = $a_column_name;
			return true;
		}
		
		// obj_pk wasn't set and holds default value specified in constructor
		return false;
	}
	
	function buildJoin()
	{
		if ($this->table_obj_reference)
		{
			return "LEFT JOIN ".$this->table_obj_reference." ON ".$this->table_tree.".child=".$this->table_obj_reference.".".$this->ref_pk." ".
				   "LEFT JOIN ".$this->table_obj_data." ON ".$this->table_obj_reference.".".$this->obj_pk."=".$this->table_obj_data.".".$this->obj_pk." ";
		}
		else
		{
			return "LEFT JOIN ".$this->table_obj_data." ON ".$this->table_tree.".child=".$this->table_obj_data.".".$this->obj_pk." ";
		}
	}

	/**
	* get leaf-nodes of given tree
	* if no tree_id was given, uses default tree in $this->tree_id
	* 
	* @param	integer	tree_id
	* @access	public
	*/
	function getLeafs($a_tree_id = 0)
	{
		if (!$a_tree_id)
		{
			$a_tree_id = $this->tree->id;
		}
		
		$q = "SELECT * FROM ".$this->table_tree." ".
			 $this->buildJoin().
			 "WHERE lft = (rgt -1) ".
			 "AND tree = '".$a_tree_id."'";
		
		$r = $this->ilias->db->query($q);
		
		while ($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$leafs[] = $this->fetchNodeData($row);
		}

		$this->Leafs = $leafs;

		return $leafs;
	}

	/**
	* get subnodes of given node
	* @access	public
	* @param	integer		node_id
	* @param	string		sort order of returned childs, optional (possible values: 'title','desc','last_update' or 'type')
	* @param	string		sort direction, optional (passible values: 'DESC' or 'ASC'; defalut is 'ASC')
	* @return	boolean		true when node has childs, otherwise false
	*/
	function getChilds($a_node_id, $a_order = "", $a_direction = "ASC")
	{
		// number of childs
		$count = 0;

		// init order_clause
		$order_clause = "";

		// init childs
		$this->Childs = array();

		// set order_clause if sort order parameter is given
		if (!empty($a_order))
		{
			$order_clause = "ORDER BY '".$a_order."'".$a_direction;
		}

		$q = "SELECT * FROM ".$this->table_tree." ".
			 $this->buildJoin().
			 "WHERE parent = '".$a_node_id."' ".
			 "AND tree = '".$this->tree_id."' ".
			 $order_clause;

		$r = $this->ilias->db->query($q);

		$count = $r->numRows();

		if ($count > 0)
		{
			while ($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
			{
				//$this->Childs[] = $this->fetchNodeData($row);
				$tmp = $this->fetchNodeData($row);
				$this->Childs[] = $tmp;
			}

			// mark the last child node (important for display)
			$this->Childs[$count - 1]["last"] = true;

			return $this->Childs;
		}
		else
		{
			return array();
		}
	}

	/**
	* get subnodes of given node by type
	* @access	public
	* @param	integer	node_id
	* @param	integer	parent_id
	* @param	string	object type definition
	* @return	array	childs by type
	* // TODO: we only need one node_id
	*/
	function getAllChildsByType($a_node_id,$a_parent_id,$a_type)
	{
		$data = array();	// node_data
		$row = "";			// fetched row
		$left = "";			// tree_left
		$right = "";		// tree_right

		$q = "SELECT * FROM ".$this->table_tree." ".
//			 "WHERE child = '".$a_node_id."' ".
			 "AND parent = '".$a_parent_id."' ".
			 "AND tree = '".$this->tree_id."'";
		$r = $this->ilias->db->query($q);
	
		while ($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$left = $row->lft;
			$right = $row->rgt;
		}

		$q = "SELECT * FROM ".$this->table_tree." ".
			 $this->buildJoin().
			 //"LEFT JOIN $this->table_obj_data ON $this->table_tree.child = $this->table_obj_data.obj_id ".
			 "WHERE ".$this->table_obj_data.".type = '".$a_type."' ".
			 "AND ".$this->table_tree.".lft BETWEEN '".$left."' AND '".$right."' ".
			 "AND ".$this->table_tree.".rgt BETWEEN '".$left."' AND '".$right."' ".
			 "AND ".$this->table_tree.".tree = '".$this->tree_id."'";

		$r = $this->ilias->db->query($q);
		
		while ($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$data[] = $this->fetchNodeData($row);
		}

		return $data;
	}
	/**
	* insert node under parent node
	* @access	public
	* @param	integer		node_id
	* @param	integer		parent_id (optional)
	*/
	function insertNode($a_node_id,$a_parent_id)
	{
		// get left value
		$q = "SELECT * FROM ".$this->table_tree." ".
			 "WHERE child = '".$a_parent_id."' ".
			 "AND tree = '".$this->tree_id."'";
		$r = $this->ilias->db->getRow($q);

		$left = $r->lft;

		$lft = $left + 1;
		$rgt = $left + 2;
		
//		var_dump("<pre>","child = ".$a_parent_id,"parent = ".$a_parent_parent_id,"left = ".$left,"lft = ".$lft,"rgt = ".$rgt,"</pre");
		// spread tree
		$q = "UPDATE ".$this->table_tree." SET ".
			 "lft = CASE ".
			 "WHEN lft > ".$left." ".
			 "THEN lft + 2 ".
			 "ELSE lft ".
			 "END, ".
			 "rgt = CASE ".
			 "WHEN rgt > ".$left." ".
			 "THEN rgt + 2 ".
			 "ELSE rgt ".
			 "END ".
			 "WHERE tree = '".$this->tree_id."'";
		$this->ilias->db->query($q);
		
		// get depth
		$depth = $this->getDepth($a_parent_id) + 1;

		// insert node
		$q = "INSERT INTO ".$this->table_tree." (tree,child,parent,lft,rgt,depth) ".
			 "VALUES ".
			 "('".$this->tree_id."','".$a_node_id."','".$a_parent_id."','".$lft."','".$rgt."','".$depth."')";
		$this->ilias->db->query($q);
	}

	/**
	* get all nodes in the subtree under specified node
	* 
	* @access	public
	* @param	array		node_data
	* @return	array		2-dim (int/array) key, node_data of each subtree node including the specified node
	*/
	
	function getSubTree($a_node)
	{
	    $subtree = array();
	
		$q = "SELECT * FROM ".$this->table_tree." ".
			 $this->buildJoin(). // TODO: i think this will not work
			 "WHERE ".$this->table_obj_data.".".$this->obj_pk." = ".$this->table_tree.".child ".
			 "AND ".$this->table_tree.".lft BETWEEN '".$a_node["lft"]."' AND '".$a_node["rgt"]."' ".
			 "AND ".$this->table_tree.".tree = '".$this->tree_id."' ".
			 "ORDER BY ".$this->table_tree.".lft";

		$r = $this->ilias->db->query($q);
		
		while ($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$subtree[] = $this->fetchNodeData($row);
		}
			
		return $subtree;
	}
	
	/**
	* delete node and the whole subtree under this node
	* @access	public
	* @param	array		node_data of a node
	*/
	function deleteTree($a_node)
	{
		// GET LEFT AND RIGHT VALUES
		$q = "SELECT * FROM ".$this->table_tree." ".
			 "WHERE tree = '".$a_node["tree"]."' ".
			 "AND child = '".$a_node["obj_id"]."' ";
//			 "AND parent = '".$a_node["parent"]."'";

		$r = $this->ilias->db->query($q);

		while($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$a_node["lft"] = $row->lft;
			$a_node["rgt"] = $row->rgt;
		}

		$diff = $a_node["rgt"] - $a_node["lft"] + 1;

		// delete subtree
		$q = "DELETE FROM ".$this->table_tree." ".
			 "WHERE lft BETWEEN '".$a_node["lft"]."' AND '".$a_node["rgt"]." '".
			 "AND tree = '".$a_node["tree"]."'";
		$this->ilias->db->query($q);

		// close gaps
		$q = "UPDATE ".$this->table_tree." SET ".
			 "lft = CASE ".
			 "WHEN lft > '".$a_node["lft"]." '".
			 "THEN lft - '".$diff." '".
			 "ELSE lft ".
			 "END, ".
			 "rgt = CASE ".
			 "WHEN rgt > '".$a_node["lft"]." '".
			 "THEN rgt - '".$diff." '".
			 "ELSE rgt ".
			 "END ".
			 "WHERE tree = '".$a_node["tree"]."'";
		$this->ilias->db->query($q);

		$this->parent_id = $a_node["parent"];
	}

	/**
	* get path from a given startnode to a given endnode
	* if startnode is not given the rootnode is startnode
	* @access	private
	* @param	integer		node_id of endnode 
	* @param	integer		node_id of startnode (optional)
	* @return	object		query result
	*/
	function fetchPath ($a_endnode, $a_startnode)
	{
		if (empty($a_startnode))
		{
			$a_startnode = $this->root_id;
		}

		if ($this->table_obj_reference)
		{
			$leftjoin = "LEFT JOIN ".$this->table_obj_reference." ON T2.child=".$this->table_obj_reference.".".$this->ref_pk." ".
						"LEFT JOIN ".$this->table_obj_data." ON ".$this->table_obj_reference.".".$this->obj_pk."=".$this->table_obj_data.".".$this->obj_pk." ";
		}
		else
		{
			$leftjoin = "LEFT JOIN ".$this->table_obj_data." ON T2.child=".$this->table_obj_data.".".$this->obj_pk." ";
		}

		$q = "SELECT ".$this->table_obj_data.".title,".$this->table_obj_data.".type,T2.child,(T2.rgt - T2.lft) AS sort_col ".
			 "FROM ".$this->table_tree." AS T1, ".$this->table_tree." AS T2, ".$this->table_tree." AS T3 ".
			 $leftjoin.
			 "WHERE T1.child = '".$a_startnode."' ".
			 "AND T3.child = '".$a_endnode."' ".
			 "AND T2.lft BETWEEN T1.lft AND T1.rgt ".
			 "AND T3.lft BETWEEN T2.lft AND T2.rgt ".
			 "AND T1.tree = '".$this->tree_id." '".
			 "AND T2.tree = '".$this->tree_id." '".
			 "AND T3.tree = '".$this->tree_id." '".
			 "ORDER BY sort_col DESC";

		$r = $this->ilias->db->query($q);
		
		if ($r->numRows() > 0)
		{
			return $r;
		}
		else
		{
			$this->ilias->raiseError("Error in class.tree.php: No path found!".
				" startnode:".$a_startnode.", endnode:".$a_endnode,$this->ilias->error_obj->WARNING);
		}
	}

	/**
	* get path from a given startnode to a given endnode
	* if startnode is not given the rootnode is startnode
	* if endnode is not given the current node is endnode
	* @access	public
	* @param	integer	node_id of endnode (optional)
	* @param	integer	node_id of endparent (optional)
	* @param	integer	node_id of startnode (optional)
	* @param	integer	node_id of startparent (optional)
	* @return	array	ordered path info (id,title,parent) from start to end
	*/
	function getPathFull ($a_endnode, $a_startnode = 0)
	{
		$this->Path = "";

		$r = $this->fetchPath($a_endnode, $a_startnode);
				
		while ($data = $r->fetchRow(DB_FETCHMODE_ASSOC))
		{
			$this->Path[] = array(
							   "id"		=> $data["child"],
							   "title"	=> $data["title"],
							   "type"   => $data["type"],
							   "parent"	=> $data["parent"]
							   );
		}

		return $this->Path;
	}	

	/**
	* get path from a given startnode to a given endnode
	* if startnode is not given the rootnode is startnode
	* if endnode is not given the current node is endnode
	* @access	public
	* @param	integer		node_id of endnode (optional)
	* @param	integer		node_id of endparentnode (optional)
	* @param	integer		node_id of startnode (optional)
	* @param	integer		node_id of startparentnode (optional)
	* @return	array		all path ids from startnode to endnode
	*/
	function getPathId ($a_endnode, $a_startnode = 0)
	{
		$r = $this->fetchPath($a_endnode, $a_startnode);
		
		while ($data = $r->fetchRow(DB_FETCHMODE_ASSOC))
		{
			$arr[] = $data["child"];
		}
		
		return $arr;
	}
	
	/**
	* check consistence of tree
	* @access	public
	* @return	boolean		true if tree is ok; otherwise throws error object
	*/
	function checkTree()
	{
		$q = "SELECT lft,rgt FROM ".$this->table_tree." ".
			 "WHERE tree = '".$this->tree_id."'";
				 
		$r = $this->ilias->db->query($q);

		while ($data = $r->fetchRow(DB_FETCHMODE_ASSOC))
		{
			$lft[] = $data["lft"];
			$rgt[] = $data["rgt"];
		}
			
		$all = array_merge($lft,$rgt);
		$uni = array_unique($all);
			
		if (count($all) != count($uni))
		{
			$this->ilias->raiseError("Error: Tree is corrupted!!",$this->ilias->error_obj->WARNING);
		}
		
		return true;
	}

	/**
	* fetch all expanded nodes & their childs
	* @access	public
	* @param	array	tree information
	* @return	array 	all expanded nodes & their childs
	*/
	function buildTree ($nodes)
	{
		foreach ($nodes as $val)
		{
			$knoten[$val] = $this->getChilds($val);
		}
	
		return $knoten;		
	}

	/**
	* get all childs from a node by depth
	* @access	public
	* @param	integer		tree-level
	* @param	integer		node_id
	* @return	array		childs
	*/
	function getChildsByDepth($a_depth,$a_parent)
	{
		// to reset the content
		$this->Childs = array();

		$q = "SELECT * FROM ".$this->table_tree." ".
			 $this->buildJoin().
			 "WHERE depth = '".$a_depth."' ".
			 "AND parent = '".$a_parent."' ".
			 "AND tree = '".$this->tree_id."'";

		$r = $this->ilias->db->query($q);

		$count = $r->numRows();

		if ($r->numRows() > 0)
		{
			while ($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
			{
				$this->Childs[] = $this->fetchNodeData($row);
			}

			$this->Childs[$count - 1]["last"] = true;

			return $this->Childs;
		}

		return false;
	}
	
	/**
	* Return the maximum depth in tree
	* @access	public
	* @return	integer
	*/
	function getMaximumDepth()
	{
		$q = "SELECT MAX(depth) FROM ".$this->table_tree;
		$r = $this->ilias->db->query($q);
		
		$row = $r->fetchRow();
		return $row[0];
	}
	
	/**
	* Return depth of an object
	* @access	private
	* @param	integer		node_id of parent's node_id
	* @param	integer		node_id of parent's node parent_id
	* @return	integer		depth of node
	*/
	function getDepth($a_node_id)
	{
		if ($a_parent_id)
		{
			$q = "SELECT depth FROM ".$this->table_tree." ".
				 "WHERE child = '".$a_node_id."' ".
				 "AND tree = '".$this->tree_id."'";
	
			$r = $this->ilias->db->getRow($q);
	
			return $r->depth;
		}
		else
		{
			return 1;
		}
	}

	/**
	* Calculates additional information for each node in tree-structure:
	* no. of successors:	How many successors does the node have?
	* 						Every node under the concerned node in the tree counts as a successor. 
	* depth			   :	The depth-level in tree the concerned node has. (the root node has a depth of 1!)
	* brother		   :	The no. of node which are on the same depth-level with the concerned node
	*
	* @access	public
	* @param	integer		tree_id (optional)
	* @return	array		array of new tree information (to be specified.... :-)
	*/
	function calculateFlatTree($a_tree_id = 0)
	{
		if (empty($a_tree_id))
		{
		    $a_tree_id = $this->tree_id;
		}
		
		if ($this->table_obj_reference)
		{
			$leftjoin = "LEFT JOIN ".$this->table_obj_reference." ON s.child=".$this->table_obj_reference.".".$this->ref_pk." ".
						"LEFT JOIN ".$this->table_obj_data." ON ".$this->table_obj_reference.".".$this->obj_pk."=".$this->table_obj_data.".".$this->obj_pk." ";
		}
		else
		{
			$leftjoin = "LEFT JOIN ".$this->table_obj_data." ON s.child=".$this->table_obj_data.".".$this->obj_pk." ";

		}

		$q = "SELECT s.child,s.lft,s.rgt,title,s.depth,".
			 "(s.rgt-s.lft-1)/2 AS successor,".
			 "((min(v.rgt)-s.rgt-(s.lft>1))/2) > 0 AS brother ".
			 "FROM ".$this->table_tree." v, ".$this->table_tree." s ".
			 $leftjoin.
			 "WHERE s.lft BETWEEN v.lft AND v.rgt ".
			 "AND (v.child != s.child OR s.lft = '1') ".
			 "AND s.tree = '".$a_tree_id."' ".
			 "AND v.tree = '".$a_tree_id."' ".
			 "GROUP BY s.child ".
			 "ORDER BY s.lft";
		$r = $this->ilias->db->query($q);
		
		while ($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$arr[] = array(
							"title"		=> $row->title,
							"child"		=> $row->child,
							"successor" => $row->successor,
							"depth"		=> $row->depth,
							"brother"	=> $row->brother,
							"lft"		=> $row->lft,
							"rgt"		=> $row->rgt
						   );
		}
		
		return $arr;
	}

	/**
	* get all information of a node.
	* get data of a specific node from tree and object_data
	* @access	public
	* @param	integer		node id
	* @return	object		db result object
	*/
	function getNodeData($a_node_id)
	{
		$q = "SELECT * FROM ".$this->table_tree." ".
			 $this->buildJoin().
			 "WHERE ".$this->table_tree.".child = '".$a_node_id."' ".
//			 "AND $this->table_tree.parent = '".$a_parent_id."' ".
			 "AND ".$this->table_tree.".tree = '".$this->tree_id."'";
		$r = $this->ilias->db->query($q);
		
		$row = $r->fetchRow(DB_FETCHMODE_OBJECT);

		return $this->fetchNodeData($row);
	}

	/**
	* get data of parent node from tree and object_data
	* @access	private
 	* @param	object	db	db result object containing node_data
	* @return	array		2-dim (int/str) node_data
	*/
	function fetchNodeData($a_row)
	{
		$data = array(
					"ref_id"		=> $a_row->ref_id,
					"type"			=> $a_row->type,
					"title"			=> $a_row->title,
					"description"	=> $a_row->description,
					"owner"			=> $a_row->owner,
					"create_date"	=> $a_row->create_date,
					"last_update"	=> $a_row->last_update,
					"tree"			=> $a_row->tree,
					"child"			=> $a_row->child,
					"parent"		=> $a_row->parent,
					"lft"			=> $a_row->lft,
					"rgt"			=> $a_row->rgt,
					"depth"			=> $a_row->depth,
					"desc"			=> $a_row->description,
					"id"			=> $a_row->obj_id
					);
		return $data ? $data : array();
	}

	/**
	* get data of parent node from tree and object_data
	* @access	public
 	* @param	integer		node id
	* @return	array
	*/
	function getParentNodeData($a_node_id)
	{
		if ($this->table_obj_reference)
		{
			$leftjoin = "LEFT JOIN ".$this->table_obj_reference." ON v.child=".$this->table_obj_reference.".".$this->obj_pk." ".
				  		"LEFT JOIN ".$this->table_obj_data." ON ".$this->table_obj_reference.".".$this->obj_pk."=".$this->table_obj_data.".".$this->obj_pk." ";
		}
		else
		{
			$leftjoin = ",".$this->table_obj_data." ";
		}

		$q = "SELECT * FROM ".$this->table_tree." s,".$this->table_tree." v ".
			 $leftjoin.
			 "WHERE ".$this->table_obj_data.".".$this->obj_pk." = v.child ".
			 "AND s.child = '".$a_node_id."' ".
			 //"AND s.parent = '".$a_parent_id."' ".	// maybe deprecated
			 "AND s.parent = v.child ".
			 "AND s.lft > v.lft ".
			 "AND s.rgt < v.rgt ".
			 "AND s.tree = '".$this->tree_id."' ".
			 "AND v.tree = '".$this->tree_id."'";

		$r = $this->ilias->db->query($q);

		$row = $r->fetchRow(DB_FETCHMODE_OBJECT);
		
		return $this->fetchNodeData($row);
	}

	/**
	* checks if a node is in the path of an other node
	* @access	public
 	* @param	integer		object id of start node
	* @param	integer		parent id of start node
	* @param    integer     object id of query node
	* @param    integer     parent id of query node
	* @return	integer		number of entries
	*/
	function isGrandChild($a_start_node,$a_query_node)
	{
		$q = "SELECT * FROM ".$this->table_tree." s,".$this->table_tree." v ".
			 "WHERE s.child = '".$a_start_node."' ".
			 //"AND s.parent = '".$a_start_parent."' ".
			 "AND v.child = '".$a_query_node."' ".
			 //"AND v.parent = '".$a_query_parent."' ".
			 "AND s.tree = '".$this->tree_id."' ".
			 "AND v.tree = '".$this->tree_id."' ".
			 "AND v.lft BETWEEN s.lft AND s.rgt ".
			 "AND v.rgt BETWEEN s.lft AND s.rgt";
		$r = $this->ilias->db->query($q);

		return $r->numRows();
	}

	/**
	* create a new tree
	* to do: ???
	* @param	integer		a_tree_id: obj_id of object where tree belongs to
	* @param	integer		a_node_id: root node of tree (optional; default is tree_id itself)
	* @return	boolean		true on success
	* @access	public
	*/
	function addTree($a_tree_id,$a_node_id = -1)
	{
		if ($a_node_id <= 0)
		{
			$a_node_id = $a_tree_id;
		}
		
		$q = "INSERT INTO ".$this->table_tree." (tree, child, parent, lft, rgt, depth) ".
			 "VALUES ".
			 "('".$a_tree_id."','".$a_node_id."', 0, 1, 2, 1)";
		$this->ilias->db->query($q);
		
		return true;
	}

	/**
	* get the rootid of a tree
	* to do: ???
	* @param	integer		a_tree_id: obj_id of object where tree belongs to
	* @access	public
	*/
	function getRootID($a_tree_id)
	{
		$q = "SELECT * FROM ".$this->table_tree." WHERE tree='".$a_tree_id."' AND parent='0'";
		$r = $this->ilias->db->query($q);
		$row = $r->fetchRow(DB_FETCHMODE_OBJECT);

		return $this->fetchNodeData($row);			
	}

	/**
	* get nodes by type
	* to do: ???
	* @param	integer		a_tree_id: obj_id of object where tree belongs to
	* @param	integer		a_type_id: type of object
	* @access	public
	*/
	function getNodeDataByType($a_type)
	{
		$data = array();	// node_data
		$row = "";			// fetched row
		$left = "";			// tree_left
		$right = "";		// tree_right

		$q = "SELECT * FROM ".$this->table_tree." ".
			 "WHERE tree = '".$this->tree_id."'".
			 "AND parent = '0'";

		$r = $this->ilias->db->query($q);
	
		while ($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$left = $row->lft;
			$right = $row->rgt;
		}

		$q = "SELECT * FROM ".$this->table_tree." ".
			 $this->buildJoin().
//			 "LEFT JOIN $this->table_obj_data ON $this->table_tree.child = $this->table_obj_data.obj_id ".
			 "WHERE ".$this->table_obj_data.".type = '".$a_type."' ".
			 "AND ".$this->table_tree.".lft BETWEEN '".$left."' AND '".$right."' ".
			 "AND ".$this->table_tree.".rgt BETWEEN '".$left."' AND '".$right."' ".
			 "AND ".$this->table_tree.".tree = '".$this->tree_id."'";

		$r = $this->ilias->db->query($q);
		
		while ($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$data[] = $this->fetchNodeData($row);
		}
		
		return $data;
	}

	

	/**
	* remove an existing tree
	*
	* @param	integer		a_tree_id: tree to be removed
	* @return	boolean		true on success
	* @access	public
 	*/
	function removeTree($a_tree_id)
	{
		if (!$a_tree_id)
		{
			$this->ilias->raiseError("No tree_id given! Action aborted",$this->ilias->error_obj->MESSAGE);
		}
		
		$q = "DELETE FROM ".$this->table_tree." WHERE tree = '".$a_tree_id."'";
		$this->ilias->db->query($q);
		
		return true;
	}

	/**
	* DEPRECATED
	* get number of references of a specific object
 	* @param	integer	tree_id
 	* @param	integer	obj_id
	* @return	integer
	* @access	public
	*/
	function countTreeEntriesOfObject($a_tree_id,$a_obj_id)
	{
		$q = "SELECT * FROM ".$this->table_tree." ".
			 "WHERE tree = '".$a_tree_id."' ".
			 "AND child = '".$a_obj_id."'";

		$r = $this->ilias->db->query($q);
		return $r->numRows();
	}
	/**
	* save subtree: copy a subtree (defined by obj_id and parent) to a new tree
	* with tree_id -obj_id.This is neccessary for cut/copy   
	* @param	integer	tree_id
	* @param	integer	obj_id
	* @param	integer	parent
	* @return	integer
	* @access	public
	*/
	function saveSubtree($a_obj_id,$a_parent,$a_tree)
	{
		// GET LEFT AND RIGHT VALUE
		$q = "SELECT * FROM ".$this->table_tree." ".
			 "WHERE tree = '".$a_tree."' ".
			 "AND child = '".$a_obj_id."' ".
			 "AND parent = '".$a_parent."'";
		$r = $this->ilias->db->query($q);
	
		while($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$lft = $row->lft;
			$rgt = $row->rgt;
		}
		// GET ALL SUBNODES
		$q = "SELECT * FROM ".$this->table_tree." ".
			 "WHERE tree = '".$a_tree."' ".
			 "AND lft >= '".$lft."' ".
			 "AND rgt <= '".$rgt."'";
		$r = $this->ilias->db->query($q);

		while($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$subnodes[$row->child]["tree"]   = $row->tree;
			$subnodes[$row->child]["child"]  = $row->child;
 			$subnodes[$row->child]["parent"] = $row->parent;
			$subnodes[$row->child]["lft"]    = $row->lft;
			$subnodes[$row->child]["rgt"]    = $row->rgt;
			$subnodes[$row->child]["depth"]  = $row->depth;
		}

		// SAVE SUBTREE
		foreach($subnodes as $node)
		{
			$q = "INSERT INTO ".$this->table_tree." ".
				 "VALUES ('".-$a_obj_id."','".$node["child"]."','".$node["parent"]."','".
				 $node["lft"]."','".$node["rgt"]."','".$node["depth"]."')";
			$r = $this->ilias->db->query($q);
		}

		return true;
	}

	/**
	* save node: copy a node (defined by obj_id and parent) to a new tree
	* with tree_id -obj_id.This is neccessary for link
	* @param	integer	tree_id
	* @param	integer	obj_id
	* @param	integer	parent
	* @return	integer
	* @access	public
	*/
	function saveNode($a_obj_id,$a_parent,$a_tree)
	{
		// SAVE NODE
		$q = "INSERT INTO ".$this->table_tree." ".
			 "VALUES ('".-$a_obj_id."','".$a_obj_id."','".$a_parent."','1','2','1')";
		$r = $this->ilias->db->query($q);

		return true;
	}

	/**
	* get data saved/deleted nodes
	* @return	array	data
	* @param	integer	id of parent object of saved object
	* @access	public
	*/
	function getSavedNodeData($a_parent)
	{
		$q = "SELECT * FROM ".$this->table_tree.",".$this->table_obj_data." ".
			 "WHERE ".$this->table_tree.".tree < 0 ".
			 "AND ".$this->table_tree.".parent = '".$a_parent."' ".
			 "AND ".$this->table_tree.".child = ".$this->table_obj_data.".".$this->obj_pk;
		
		$r = $this->ilias->db->query($q);

		while($row = $r->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$saved[] = $this->fetchNodeData($row);
		}
		return $saved;
	}
	
	/**
	* get parent id of given node
	* @access	public
	* @param	integer	node id
	* @return	integer	parent id
	*/
	function getParent($a_node_id)
	{
		$q = "SELECT parent FROM ".$this->table_tree." ".
			 "WHERE child='".$a_node_id."' ".
			 "AND tree='".$this->tree_id."'";
		$r = $this->ilias->db->query($q);
		
		$row = $r->fetchRow(DB_FETCHMODE_OBJECT);
		return $row->parent;
	}
} // END class.tree
?>
