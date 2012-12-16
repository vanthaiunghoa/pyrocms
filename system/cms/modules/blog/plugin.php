<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Blog Plugin
 *
 * Create lists of posts
 * 
 * @author		PyroCMS Dev Team
 * @package		PyroCMS\Core\Modules\Blog\Plugins
 */
class Plugin_Blog extends Plugin
{

	public $version = '1.0.0';
	public $name = array(
		'en' => 'Blog',
	);
	public $description = array(
		'en' => 'A plugin to display information such as blog categories and posts.',
	);

	/**
	 * Returns a PluginDoc array
	 *
	 * @return array
	 */
	public function _self_doc()
	{

		$info = array(
			'posts' => array(
				'single' => false,// single tag or double tag (tag pair)
				'double' => true,
				'variables' => '',// the variables available inside the double tags
				'params' => array(// an array of all attributes
					'category' => array(// the attribute name. If the attribute name is used give most common values as separate attributes
						'type' => '@slug',// Can be: @number, @flag, @text, @any. A flag is a predefined value.
						'flags' => '',// valid flag values that the plugin will recognize. IE: asc|desc|random
						'default' => '',// the value that it defaults to
						'required' => false,// is this attribute required?
						),
					'limit' => array(
						'type' => '@number',
						'flags' => '',
						'default' => '10',
						'required' => false,
						),
					'offset' => array(
						'type' => '@number',
						'flags' => '',
						'default' => '0',
						'required' => false,
						),
					'order-by' => array(
						'type' => '@column',
						'flags' => '',
						'default' => 'created_on',
						'required' => false,
						),
					'order-dir' => array(
						'type' => '@flag',
						'flags' => 'asc|desc|random',
						'default' => 'asc',
						'required' => false,
						),
					),
				),
			'categories' => array(
				'single' => false,
				'double' => true,
				'variables' => 'title|slug|url',
				'params' => array(
					'limit' => array(
						'type' => '@number',
						'flags' => '',
						'default' => '10',
						'required' => false,
						),
					'order-by' => array(
						'type' => '@flag',
						'flags' => 'id|title',
						'default' => 'title',
						'required' => false,
						),
					'order-dir' => array(
						'type' => '@flag',
						'flags' => 'asc|desc|random',
						'default' => 'asc',
						'required' => false,
						),
					),
				),
			'count_posts' => array(
				'single' => true,
				'double' => false,
				'variables' => '',
				'params' => array(
					'category_id' => array(
						'type' => '@number',
						'flags' => '',
						'default' => '',
						'required' => false,
						),
					'author_id' => array(
						'type' => '@number',
						'flags' => '',
						'default' => '',
						'required' => false,
						),
					'status' => array(
						'type' => '@flag',
						'flags' => 'live|draft',
						'default' => '',
						'required' => false,
						),
					),
				),
			// method name
			'tags' => array(
				'single' => false,
				'double' => true,
				'variables' => 'title|url',
				'params' => array(
					'limit' => array(
						'type' => '@number',
						'flags' => '',
						'default' => '10',
						'required' => false,
						),
					),
				),
			);

		return $info;
	}

	/**
	 * Blog List
	 *
	 * Creates a list of blog posts
	 *
	 * Usage:
	 * {{ blog:posts order-by="title" limit="5" }}
	 *		<h2>{{ title }}</h2>
	 *		<p> {{ body }} </p>
	 * {{ /blog:posts }}
	 *
	 * @param	array
	 * @return	array
	 */
	public function posts()
	{
		$limit		= $this->attribute('limit', 10);
		$offset		= $this->attribute('offset', 0);
		$category	= $this->attribute('category');
		$order_by 	= $this->attribute('order-by', 'created_on');
		$order_dir	= $this->attribute('order-dir', 'ASC');

		if ($category)
		{
			$categories = explode('|', $category);
			$category = array_shift($categories);

			$this->db->where('blog_categories.' . (is_numeric($category) ? 'id' : 'slug'), $category);

			foreach($categories as $category)
			{
				$this->db->or_where('blog_categories.' . (is_numeric($category) ? 'id' : 'slug'), $category);
			}
		}

		$posts = $this->db
			->select('blog.*')
			->select('blog_categories.title as category_title, blog_categories.slug as category_slug')
			->select('p.display_name as author_name')
			->where('status', 'live')
			->where('created_on <=', now())
			->join('blog_categories', 'blog.category_id = blog_categories.id', 'left')
			->join('profiles p', 'blog.author_id = p.user_id', 'left')
			->order_by('blog.' . $order_by, $order_dir)
			->limit($limit,$offset)
			->get('blog')
			->result();
		$i = 1;
		foreach ($posts as &$post)
		{
			$post->url = site_url('blog/'.date('Y', $post->created_on).'/'.date('m', $post->created_on).'/'.$post->slug);
			$post->count = $i++;
		}
		
		return $posts;
	}

	/**
	 * Categories
	 *
	 * Creates a list of blog categories
	 *
	 * Usage:
	 * {{ blog:categories order-by="title" limit="5" }}
	 *		<a href="{{ url }}" class="{{ slug }}">{{ title }}</a>
	 * {{ /blog:categories }}
	 *
	 * @param	array
	 * @return	array
	 */
	public function categories()
	{
		$limit		= $this->attribute('limit', 10);
		$order_by 	= $this->attribute('order-by', 'title');
		$order_dir	= $this->attribute('order-dir', 'ASC');

		$categories = $this->db
			->select('title, slug')
			->order_by($order_by, $order_dir)
			->limit($limit)
			->get('blog_categories')
			->result();

		foreach ($categories as &$category)
		{
			$category->url = site_url('blog/category/'.$category->slug);
		}
		
		return $categories;
	}

	/**
	 * Count Posts By Column
	 *
	 * Usage:
	 * {{ blog:count_posts author_id="1" }}
	 *
	 * The attribute name is the database column and 
	 * the attribute value is the where value
	 */
	public function count_posts()
	{
		$wheres = $this->attributes();

		// make sure they provided a where clause
		if (count($wheres) == 0) return false;

		foreach ($wheres AS $column => $value)
		{
			$this->db->where($column, $value);
		}

		return $this->db->count_all_results('blog');
	}
	
	/**
	 * Tag/Keyword List
	 *
	 * Create a list of blog keywords/tags
	 *
	 * Usage:
	 * {{ blog:tags limit="10" }}
	 *		<span><a href="{{ url }}" title="{{ title }}">{{ title }}</a></span>
	 * {{ /blog:tags }}
	 *
	 * @param	array
	 * @return	array
	 */	
	public function tags()
	{
		$limit = $this->attribute('limit', 10);
		
		$this->load->library(array('keywords/keywords'));

		$posts = $this->db->select('keywords')->get('blog')->result();

		$buffer = array(); // stores already added keywords
		$tags   = array();

		foreach($posts as $p)
		{
			$kw = Keywords::get_array($p->keywords);

			foreach($kw as $k)
			{
				$k = trim(strtolower($k));

				if(!in_array($k, $buffer)) // let's force a unique list
				{
					$buffer[] = $k;

					$tags[] = array(
						'title' => ucfirst($k),
						'url'   => site_url('blog/tagged/'.$k)
					);
				}
			}
		}
		
		if(count($tags) > $limit) // Enforce the limit
		{
			return array_slice($tags, 0, $limit);
		}
	
		return $tags;
	}
		
}

/* End of file plugin.php */