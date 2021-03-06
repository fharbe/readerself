<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Feeds extends CI_Controller {
	function __construct() {
		parent::__construct();
	}
	public function index() {
		if(!$this->session->userdata('mbr_id')) {
			redirect(base_url());
		}

		$data = array();

		$filters = array();
		$filters[$this->router->class.'_feeds_fed_title'] = array('fed.fed_title', 'like');
		$flt = $this->readerself_library->build_filters($filters);
		$flt[] = 'fed.fed_id IS NOT NULL';
		$results = $this->readerself_model->get_feeds_total($flt);
		$build_pagination = $this->readerself_library->build_pagination($results->count, 20, $this->router->class.'_feeds');
		$data = array();
		$data['pagination'] = $build_pagination['output'];
		$data['position'] = $build_pagination['position'];
		$data['feeds'] = $this->readerself_model->get_feeds_rows($flt, $build_pagination['limit'], $build_pagination['start'], 'subscribers DESC');

		$content = $this->load->view('feeds_index', $data, TRUE);
		$this->readerself_library->set_content($content);
	}
	public function subscribe($fed_id) {
		if(!$this->session->userdata('mbr_id')) {
			redirect(base_url().'?u='.$this->input->get('u'));
		}

		$this->load->library(array('form_validation'));
		$data = array();
		$data['fed'] = $this->readerself_model->get_feed_row($fed_id);
		if($data['fed']) {
			if($this->config->item('folders')) {
				$query = $this->db->query('SELECT flr.* FROM '.$this->db->dbprefix('folders').' AS flr WHERE flr.mbr_id = ? GROUP BY flr.flr_id ORDER BY flr.flr_title ASC', array($this->member->mbr_id));
				$data['folders'] = array();
				$data['folders'][0] = $this->lang->line('no_folder');
				if($query->num_rows() > 0) {
					foreach($query->result() as $flr) {
						$data['folders'][$flr->flr_id] = $flr->flr_title;
					}
				}
			}

			if($this->config->item('folders')) {
				$this->form_validation->set_rules('folder', 'lang:folder', 'required');
			}
			$this->form_validation->set_rules('priority', 'lang:priority', 'numeric');
			$this->form_validation->set_rules('direction', 'lang:direction', '');

			if($this->form_validation->run() == FALSE) {
				$content = $this->load->view('feeds_subscribe', $data, TRUE);
				$this->readerself_library->set_content($content);
			} else {
				if($this->config->item('folders')) {
					if($this->input->post('folder')) {
						$query = $this->db->query('SELECT flr.* FROM '.$this->db->dbprefix('folders').' AS flr WHERE flr.mbr_id = ? AND flr.flr_id = ? GROUP BY flr.flr_id', array($this->member->mbr_id, $this->input->post('folder')));
						if($query->num_rows() > 0) {
							$this->db->set('flr_id', $this->input->post('folder'));
						}
					}
				}

				$this->db->set('mbr_id', $this->member->mbr_id);
				$this->db->set('fed_id', $fed_id);
				$this->db->set('sub_priority', $this->input->post('priority'));
				$this->db->set('sub_direction', $this->input->post('direction'));
				$this->db->set('sub_datecreated', date('Y-m-d H:i:s'));
				$this->db->insert('subscriptions');

				redirect(base_url().'feeds');
			}
		} else {
			$this->index();
		}
	}
	public function delete($fed_id) {
		if(!$this->session->userdata('mbr_id')) {
			redirect(base_url());
		}

		$this->load->library('form_validation');
		$data = array();
		$data['fed'] = $this->readerself_model->get_feed_row($fed_id);
		if($data['fed']) {
			if($data['fed']->subscribers == 0) {
				$this->form_validation->set_rules('confirm', 'lang:confirm', 'required');
				if($this->form_validation->run() == FALSE) {
					$content = $this->load->view('feeds_delete', $data, TRUE);
					$this->readerself_library->set_content($content);
				} else {
					//$query = $this->db->query('DELETE FROM '.$this->db->dbprefix('categories').' WHERE itm_id IN ( SELECT itm.itm_id FROM '.$this->db->dbprefix('items').' AS itm WHERE itm.fed_id = ? GROUP BY itm.itm_id )', array($fed_id));
					//$query = $this->db->query('DELETE FROM '.$this->db->dbprefix('enclosures').' WHERE itm_id IN ( SELECT itm.itm_id FROM '.$this->db->dbprefix('items').' AS itm WHERE itm.fed_id = ? GROUP BY itm.itm_id )', array($fed_id));
					//$query = $this->db->query('DELETE FROM '.$this->db->dbprefix('favorites').' WHERE itm_id IN ( SELECT itm.itm_id FROM '.$this->db->dbprefix('items').' AS itm WHERE itm.fed_id = ? GROUP BY itm.itm_id )', array($fed_id));
					//$query = $this->db->query('DELETE FROM '.$this->db->dbprefix('history').' WHERE itm_id IN ( SELECT itm.itm_id FROM '.$this->db->dbprefix('items').' AS itm WHERE itm.fed_id = ? GROUP BY itm.itm_id )', array($fed_id));
					//$query = $this->db->query('DELETE FROM '.$this->db->dbprefix('share').' WHERE itm_id IN ( SELECT itm.itm_id FROM '.$this->db->dbprefix('items').' AS itm WHERE itm.fed_id = ? GROUP BY itm.itm_id )', array($fed_id));

					$this->db->where('fed_id', $fed_id);
					$this->db->delete('items');

					$this->db->where('fed_id', $fed_id);
					$this->db->delete('feeds');

					//$this->db->query('OPTIMIZE TABLE categories, enclosures, favorites, history, share, items, feeds');

					redirect(base_url().'feeds');
				}
			} else {
				redirect(base_url().'feeds');
			}
		} else {
			$this->index();
		}
	}
	public function export() {
		if(!$this->session->userdata('mbr_id')) {
			redirect(base_url());
		}

		$this->readerself_library->set_template('_opml');
		$this->readerself_library->set_content_type('application/xml');

		header('Content-Disposition: inline; filename="feeds-'.date('Y-m-d').'.opml";');

		$feeds = array();
		$query = $this->db->query('SELECT fed.* FROM '.$this->db->dbprefix('feeds').' AS fed WHERE fed.fed_id NOT IN( SELECT sub.fed_id FROM '.$this->db->dbprefix('subscriptions').' AS sub WHERE sub.mbr_id = ?) GROUP BY fed.fed_id', array($this->member->mbr_id));
		if($query->num_rows() > 0) {
			foreach($query->result() as $fed) {
				$feeds[] = $fed;
			}
		}

		$data = array();
		$data['feeds'] = $feeds;

		$content = $this->load->view('feeds_export', $data, TRUE);
		$this->readerself_library->set_content($content);
	}
}
