<?php 

namespace app\init;

/* Exit if accessed directly. */
if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('get_home_path')) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
}

class Sitemap {	
	/**
	 * Returns permalink of post id
	 *
	 * @since  2.0.14
	 */
	protected function getPermalinkFromId($id) {
		$post_status = get_post_status($id);
		$post_type = get_post_type_object(get_post_type($id));

		// Don't link if item is private and user does't have capability to read it.
		if ($post_status === 'private' && $post_type !== null && !current_user_can($post_type->cap->read_private_posts)) {
			return '';
		}

		$url = get_permalink($id);
		if ($url === false) {
			return '';
		}

		return $url;
	}
	
	/**
	 * Gets posts/pages and builds a basic sitemap. 
	 *
	 * @since 2.0.14
	 */
	public function buildSitemap() {
		global $wpdb;
		
		$xmlString = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . PHP_EOL . '        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . PHP_EOL . '        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">'. PHP_EOL;
		
		$postTypesQrStr = null;
		$postTypes = get_option('sseo_sitemap_post_types');
		if (is_array($postTypes)) {
			foreach($postTypes as $postType) {
				$postTypesQrStr .= " OR p.post_type='".$postType."'";
			}
		}
		
		$qs = "SELECT
			p.ID,
			p.post_author,
			p.post_status,
			p.post_name,
			p.post_parent,
			p.post_type,
			p.post_date,
			p.post_date_gmt,
			p.post_modified,
			p.post_modified_gmt,
			p.comment_count 
		FROM
			{$wpdb->posts} p
		WHERE
			p.post_password = ''
			AND p.post_status = 'publish'";
		
		if (!empty($postTypesQrStr)) {
			$qs .= " AND (".substr($postTypesQrStr, 4).")";
		}

		$qs .= " ORDER BY p.post_date_gmt ASC";

		$posts = $wpdb->get_results($qs);
		
		$priority = "1.00";
		
		$change_freq = "weekly";
		
		$loop_count = 0;
		
		foreach($posts as $post) {
						
			if ($loop_count > 0) {
				$priority = "0.80";
			}
			$loop_count++;
			
			$permalink = $this->getPermalinkFromId($post->ID);
			if (empty($permalink)) {
				continue;
			}
			$xmlString .= '   <url>' . PHP_EOL .
			'      <loc>'.htmlspecialchars($permalink).'</loc>'. PHP_EOL .
			'      <lastmod>'.date('c', strtotime($post->post_date_gmt)).'</lastmod>' . PHP_EOL .
			'      <changefreq>'.$change_freq.'</changefreq>' . PHP_EOL .
			'      <priority>'.$priority.'</priority>' . PHP_EOL .
			'   </url>' . PHP_EOL;
		}
		
		$xmlString .= '</urlset>' . PHP_EOL;

		$path = get_home_path();
		@unlink($path.'sitemap.xml');
		$file = fopen($path."sitemap.xml", "w");
		fwrite($file, $xmlString);
		fclose($file);		
	}
}

?>