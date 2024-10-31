<?php
/*
	Scuba Logger, a wordpress plugin for storing, displaying and searching a scuba dive log.
    Copyright (C) 2014 Aengus Martin

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class ScubaLoggerWp {
	private $table_main_log;        // The main table of dive records
	private $table_attributes_list; // The list of available dive attributes
	private $table_attributes_log;  // The table linking dives to their attributes
	private $dive_descriptors;      // An array in which the keys are the columns of $table_main_log and the values are the proper display names.
	private $dive_descriptors_text; // An array in which the keys are a subset of the keys of $dive_descriptors corresponding to text entries and the
									// values are the maximum length of the respective entries, e.g. $dive_descriptors_text['weather'] = 10 means at most
									// 10 characters can be entered in this field.
	private $dive_descriptors_fp;   // An array in which the keys are a subset of the keys of $dive_descriptors corresponding to numerical (floating point)
									// entries. The value is boolean indicating if the numerical entry must be positive.

	public function __construct() {
	}

	public function run() {
		global $wpdb;

		$this->table_main_log        = $wpdb->prefix . "scubalogger";
		$this->table_attributes_list = $wpdb->prefix . "scubalogger_types";
		$this->table_attributes_log  = $wpdb->prefix . "scubalogger_type_records";

		$this->dive_descriptors['divenumber']    = "Dive Number";
		$this->dive_descriptors['divedate']      = "Dive Date";
		$this->dive_descriptors['sitename']      = "Dive Site";
		$this->dive_descriptors['location']      = "Location";
		$this->dive_descriptors['objective']     = "Objective";
		$this->dive_descriptors['timedown']      = "Time Down";
		$this->dive_descriptors['maxdepth']      = "Max Depth";
		$this->dive_descriptors['avdepth']       = "Average Depth";
		$this->dive_descriptors['divetime']      = "Dive Time";
		$this->dive_descriptors['watertemp']     = "Water Temperature";
		$this->dive_descriptors['airtemp']       = "Air Temperature";
		$this->dive_descriptors['weather']       = "Weather";
		$this->dive_descriptors['seaconditions'] = "Sea Conditions";
		$this->dive_descriptors['visibility']    = "Visibility";
		$this->dive_descriptors['buddy']         = "Buddy";
		$this->dive_descriptors['boatname']      = "Boat Name";
		$this->dive_descriptors['notes']         = "Notes";

		$this->dive_descriptors_text['sitename']      = 100;
		$this->dive_descriptors_text['location']      = 100;
		$this->dive_descriptors_text['objective']     = 100;
		$this->dive_descriptors_text['weather']       = 100;
		$this->dive_descriptors_text['seaconditions'] = 100;
		$this->dive_descriptors_text['buddy']         = 100;
		$this->dive_descriptors_text['boatname']      = 100;
		$this->dive_descriptors_text['notes']         = 10000;
		
		$this->dive_descriptors_fp['maxdepth']   = true;
		$this->dive_descriptors_fp['avdepth']    = true;
		$this->dive_descriptors_fp['divetime']   = true;
		$this->dive_descriptors_fp['watertemp']  = false;
		$this->dive_descriptors_fp['airtemp']    = false;
		$this->dive_descriptors_fp['visibility'] = true;

		add_option('depth_units', 'Metres');
		add_option('temp_units', 'Celsius');

		add_action('admin_init', array($this, 'admin_init'));
		add_action('admin_menu', array($this, 'admin_menu'));		
		add_shortcode("scubalogger" , array($this, 'handle_shortcode'));
	}

	public function admin_init() {
		register_setting('scubalogger_options_group', 'depth_units', array($this, 'check_depth_unit'));
		register_setting('scubalogger_options_group', 'temp_units', array($this, 'check_temp_unit'));
		wp_register_style('scubalogger-css', plugins_url('scubalogger.css', __FILE__));
		wp_register_script('scubalogger-js', plugins_url('scubalogger.js', __FILE__ ), array('jquery'));
	}

	public function check_depth_unit($input) {
		if (strcmp(strtolower($input), 'metres') == 0 || strcmp(strtolower($input), 'feet') == 0) {
			return $input;
		}
		return 'Metres';
	}

	public function check_temp_unit($input) {
		if (strcmp(strtolower($input), 'celsius') == 0 || strcmp(strtolower($input), 'fahrenheit') == 0) {
			return $input;
		}
		return 'Celsius';
	}

	public function admin_menu() {
		$toolsPage = add_management_page(
				'Scuba Logger',
				'Scuba Logger',
				// TODO: Should perhaps revisit the capabilities here.
				'edit_posts',
				'ScubaLoggerToolsMenu',
				array($this, 'display_tools_page')
				);

		$options_page = add_options_page(
				'Scuba Logger Options', 
				'Scuba Logger', 
				'manage_options', 
				'scuba-logger-options', 
				array($this, 'display_options_page')
				);

		add_action('admin_print_styles-' . $toolsPage, array($this, 'admin_styles'));
		add_action('admin_print_scripts-' . $toolsPage, array($this, 'admin_scripts'));
	}

	public function admin_styles() {
		wp_enqueue_style('scubalogger-css');
	}

	public function admin_scripts() {
		wp_enqueue_script('scubalogger-js');	
	}

	/*
	 * Get the highest dive number.
	 */
	private function get_num_dives() {
		global $wpdb;
		$sql = "SELECT divenumber FROM $this->table_main_log";
		$results = $wpdb->get_col($sql);
		$maxnum = 0;
		for ($i = 0; $i < count($results); $i++) {
			if ($results[$i] > $maxnum) {
				$maxnum = $results[$i];
			}
		}
		return $maxnum;
	}

	/*
	 * Return the number of logged dives (the number of rows in the main log table)
	 */
	private function get_num_logged_dives() {
		global $wpdb;
		$sql = "SELECT divenumber FROM $this->table_main_log";
		$results = $wpdb->get_col($sql);
		return count($results);
	}

	/*
	 * Checks if a divenumber is already being used in the database
	 */
	private function divenumber_in_use($testnumber) {
		global $wpdb;
		$sql = "SELECT divenumber FROM $this->table_main_log";
		$results = $wpdb->get_col($sql);
		for ($i = 0; $i < count($results); $i++) {
			if ($results[$i] == $testnumber) {
				return true;
			}
		}
		return false;
	}

	/*
	 * Get the number of available dive attributes.
	 */
	private function get_num_dive_attributes() {
		global $wpdb;
		$sql = "SELECT COUNT(*) FROM $this->table_attributes_list";
		$count = $wpdb->get_var($sql);
		return $count;
	}

	/*
	 * Return array of strings corresponding to available dive attributes.
	 */
	private function get_attributes() {
		global $wpdb;
		$sql = "SELECT typeid FROM $this->table_attributes_list";
		$results = $wpdb->get_col($sql);
		$diveAttributes = array();
		for ($i = 0; $i < count($results); $i++) {
			$sql = "SELECT * FROM $this->table_attributes_list WHERE typeid=" . $results[$i];
			$result = $wpdb->get_row($sql);
			$diveAttributes[$i] = $result->type;
		}
		return $diveAttributes;
	}
	
	/*
	 * Return array of attribute ids
	 */
	private function get_attribute_ids() {
		global $wpdb;
		$sql = "SELECT typeid FROM $this->table_attributes_list";
		$results = $wpdb->get_col($sql);
		return $results;
	}
	
	/*
	 * Given a dive attribute descriptor, return its id (null if invalid attribute descriptor).
	 */
	private function map_attribute_to_id($attr) {
		global $wpdb;
		$sql = "SELECT typeid FROM " . $this->table_attributes_list . " WHERE type = '" . $attr ."'";
		$result = $wpdb->get_row($sql);
		if ($result == null ) return null;
		return $result->typeid;
	}
	
	/*
	 * Given a dive attribute id, return its descriptor (null if invalid attribute id).
	 */
	private function map_id_to_attribute($attrid) {
		global $wpdb;
		$sql = "SELECT type FROM " . $this->table_attributes_list . " WHERE typeid = " . $attrid;
		$result = $wpdb->get_row($sql);
		if ($result == null ) return null;
		return $result->type;
	}

	/*
	 * Return the sum of all the dive times in the log table
	 */
	private function get_total_time_underwater($maxdivenum = null) {
		global $wpdb;
		if (is_null($maxdivenum)) {
			$maxdivenum = $this->get_num_dives();
		}
		$sql = "SELECT divetime FROM " . $this->table_main_log . " WHERE divenumber <= " . $maxdivenum;
		$results = $wpdb->get_col($sql);
		$totaltime = 0;
		for ($i = 0; $i < count($results); $i++) {
			$totaltime = $totaltime + $results[$i];
		}
		return $totaltime;
	}
	
	/*
	 * Return an array of the strings corresponding to the attributes of a particular dive.
	 */
	private function get_dive_attributes($divenumber) {
		global $wpdb;
		$attrib_strings = array();
		$sql = "SELECT * FROM " . $this->table_attributes_log . " WHERE divenumber = " . $divenumber;
		$attrib_records = $wpdb->get_results($sql);
		foreach ($attrib_records as $attrib_record) {
			$sql = "SELECT * FROM " . $this->table_attributes_list . " WHERE typeid = " . $attrib_record->typeid;
			$attrib_row = $wpdb->get_row($sql);
			array_push($attrib_strings, $attrib_row->type);
		}
		return $attrib_strings;
	}
	
	/*
	 * Return Boolean indicating whether a dive has a particular attribute.
	 */
	private function has_dive_attribute($divenumber, $attrib_id) {
		global $wpdb;
		$sql = "SELECT * FROM " . $this->table_attributes_log . " WHERE divenumber = " . $divenumber . " AND typeid = " . $attrib_id;
		$result = $wpdb->get_row($sql);
		if ($result == null) return false;
		return true;
	}
	
	/*
	 * Return the associative array corresponding to the row of the main dive table 
	 * for a particular dive number.
	 * Returns null if dive does not exist.
	 * - assumes divenumber is an integer.
	 */
	private function get_dive_details($divenumber) {
		global $wpdb;
		$sql = "SELECT * FROM $this->table_main_log WHERE divenumber = " . $divenumber;
		$details = $wpdb->get_row($sql, ARRAY_A);
		return $details;
	}

	private function get_depth_unit_abbrev() {
		$opt_val = get_option('depth_units');
		if (strcmp($opt_val, 'Feet') == 0) {
			return "ft";
		}
		return "m";
	}

	private function get_temp_unit_abbrev() {
		$opt_val = get_option('temp_units');
		if (strcmp($opt_val, 'Fahrenheit') == 0) {
			return "F";
		}
		return "C";
	}

	/*
	 * Display Options Page
	 */
	public function display_options_page() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		echo '<div class="wrap">';
		echo '<H2>Scuba Logger Options Page</H2>';
		echo '<p>Length units are currently <b>' . strtolower(get_option('depth_units')) . '</b>; temperature units are currently <b>' . strtolower(get_option('temp_units')) . '</b>.</p>';
		?>
		<form method="post" action="options.php"> 
		<?php
		settings_fields('scubalogger_options_group');
		do_settings_sections('scubalogger_options_group');
		?>
		<table class="form-table">
        <tr valign="top">
        <th scope="row">Depth Units:</th>
		<td>
		<select name="depth_units">
			<option value="Metres" <?php if (strcmp(get_option('depth_units'),'Metres') == 0) { echo 'selected';} ?> >Metres</option>
			<option value="Feet"   <?php if (strcmp(get_option('depth_units'),'Feet') == 0)   { echo 'selected';} ?> >Feet  </option>
		</select>
		</td>
        </tr>
        <tr valign="top">
        <th scope="row">Temperature Units:</th>
		<td>
		<select name="temp_units">
			<option value="Celsius"   <?php if (strcmp(get_option('temp_units'),'Celsius') == 0)   { echo 'selected';} ?> >Celsius</option>
			<option value="Fahrenheit" <?php if (strcmp(get_option('temp_units'),'Fahrenheit') == 0) { echo 'selected';} ?> >Fahrenheit</option>
		</select>
		</td>
        </tr>
   		</table>
		<?php
		submit_button(); 
		?>
		</form>
		</div>
		<?php
		echo '</div>';
	}

	/*
	 * Display the tools page
	 * - Normal, new dive submitted, delete dive submitted, edit dive submitted, updated dive submitted
	 */
	public function display_tools_page() {
		global $wpdb;
		$sqlvalues = array();
		$prefillvalues = array();
		$prefillattribs = array();
		$ok = true;
		$doprefill = false;
		$editing = -1;
		
		/*
		 * Initialise $prefillvalues with empty values.
		 */
		foreach ($this->dive_descriptors as $key => $value) {
			$prefillvalues[$key] = NULL;
		}
		
		/*
		 * Link to edit dive has been clicked
		 */
		if (isset($_POST['edit_dive_nonce_field']) && check_admin_referer('edit_dive','edit_dive_nonce_field') && $this->is_good_integer($_REQUEST['divenumber']))	{
			
			$sql = "SELECT * FROM $this->table_main_log WHERE divenumber=" . $_REQUEST['divenumber'];
			$result = $wpdb->get_row($sql, ARRAY_A);

			foreach ($this->dive_descriptors as $key => $dive_descriptor) {
				$prefillvalues[$key] = $result[$key];
			}

			$sql = "SELECT * FROM $this->table_attributes_log WHERE divenumber = " . $result['divenumber'];
			$results = $wpdb->get_results($sql);
			foreach ($results as $result) {
				$keystr = "attrib_" . $result->typeid;
				$prefillvalues[$keystr] = 1;
			}
			$doprefill = true;
			$editing = $result->divenumber;
		}

		/*
		 * Link to delete dive has been clicked
		 */
		elseif (isset($_POST['delete_dive_nonce_field']) && check_admin_referer('delete_dive','delete_dive_nonce_field') && $this->is_good_integer($_REQUEST['divenumber'])) {
			$wpdb->delete($this->table_main_log, array('divenumber' => $_REQUEST['divenumber']));
			$wpdb->delete($this->table_attributes_log, array('divenumber' => $_REQUEST['divenumber']));
		}

		/*
		 * If a form has been submitted
		 */
		elseif (isset($_POST['dive_form_nonce_field']) && check_admin_referer('submit_dive_form','dive_form_nonce_field')) {

			/*
			 * Validate Entries
			 */
			$formErrors = array();
			$editdivenumber = intval($_REQUEST['editdivenumber']);
			$divenumber = -1;
			
			// Check the dive number is valid (positive int that's not in use unless we're editing a dive).
			if ($_REQUEST['divenumber'] == '') {
				array_push($formErrors, 'Dive number must not be empty.');
				$ok = false;
			} 
			else {
				$prefillvalues['divenumber'] = $_REQUEST['divenumber'];
				if (!$this->is_good_integer($_REQUEST['divenumber']) || intval($_REQUEST['divenumber']) < 1 || 
							($this->divenumber_in_use(intval($_REQUEST['divenumber'])) && intval($_REQUEST['divenumber']) != $editdivenumber)) {
					array_push($formErrors, 'Dive Number must be a positive integer that is not already in use and not too big (max 100,000).');
					$ok = false;
				} else {
					$divenumber = intval($_REQUEST['divenumber']);
					if ($ok) {
						$sqlvalues['divenumber'] = $divenumber;
					}
				}
			}

			// Check dive date is valid
			if ($_REQUEST['divedate'] != "") {
				$prefillvalues['divedate'] = $_REQUEST['divedate'];
				if (!$this->is_good_date($_REQUEST['divedate'])) {
					array_push($formErrors, 'Incorrect <b>Dive date</b> format. Should be: YYYY-MM-DD');
					$ok = false;
				} 
				else {
					$day   = intval(substr($_REQUEST['divedate'], 8, 2));
					$month = intval(substr($_REQUEST['divedate'], 5, 2));
					$year  = intval(substr($_REQUEST['divedate'], 0, 4));
					$sqlvalues['divedate'] = $year . "-" . $month . "-" . $day; 
				}
			}

			// Check the time down is valid
			if ($_REQUEST['timedown'] != "") {
				$prefillvalues['timedown'] = $_REQUEST['timedown'];
				if (! $this->is_good_time($_REQUEST['timedown'])) {
					array_push($formErrors, "Incorrect <b>Time Down</b> format. Should be: HH:MM. You entered: " . $_REQUEST['timedown']);
					$ok = false;
				} 
				else {
					$timevals = explode(':', $_REQUEST['timedown']);
					$hour = intval($timevals[0]);
					$minute = intval($timevals[1]);
					if ($ok) {
						$sqlvalues['timedown'] = $hour . ":" . $minute;
					}
				}
			}

			/*
			 * Go through all the (remaining) numeric fields and check them.
			 */
			foreach ($this->dive_descriptors_fp as $desc => $isplus) {
				if ($_REQUEST[$desc] != "") {
					$prefillvalues[$desc] = $_REQUEST[$desc];
					if (!$this->is_good_float($_REQUEST[$desc]) || (floatval($_REQUEST[$desc]) < 0.0 && $isplus)) {
						$error = "Invalid <b>" . $this->dive_descriptors[$desc] . "</b> entry. Should be a ";
						if ($isplus) {
							$error .= "positive ";
						}
						$error .= "number that's not too big (max: 100,000). It may have a decimal point but must not end with one.";
						array_push($formErrors,  $error);
						$ok = false;
					} else {
						if ($ok) {
							$sqlvalues[$desc] = floatval($_REQUEST[$desc]);
						}
					}
				}
			}

			/*
			 * Go through all the text fields and check them.
			 */
			foreach ($this->dive_descriptors_text as $descriptor => $value) {
				$prefillvalues[$descriptor] = $_REQUEST[$descriptor];
				if (strlen($_REQUEST[$descriptor]) > $this->dive_descriptors_text[$descriptor]) {
					array_push($formErrors, "<b>" . $this->dive_descriptors[$descriptor] . "</b> is too long (" . $value . "-character max)");
					$ok = false;
				}
				elseif ($_REQUEST[$descriptor] != "") {
					$sqlvalues[$descriptor] = $_REQUEST[$descriptor];
				}
			}

			/*
			 * Get dive attributes for prefilling if there's been an error
			 */
			foreach ($this->get_attribute_ids() as $attrib_id) {
				$key = "attrib_" . $attrib_id;
				if (array_key_exists($key, $_REQUEST)) {
					$prefillvalues[$key] = 1;
				}
			}

			if (!$ok) {
				$doprefill = true;
				// if we were editing and an error was made, need to remain in edit mode by setting the $editing variable.
				if ($editdivenumber > -1) {
					$editing = $editdivenumber;
				}
			}
			else {
				$wpdb->show_errors();
				$insertedok = true;

				if ($editdivenumber < 1) {
					$insertedok = $wpdb->insert($this->table_main_log, $sqlvalues);
				} else { // we are editing a dive: need to update
					$insertedok = $wpdb->update($this->table_main_log, $sqlvalues, array('divenumber' => $sqlvalues['divenumber']));
				}
				if ($insertedok === false) {
					echo "<p>SQL insert/update failed: \$insertedok = $insertedok</p>";
				}

				/*
				 * Insert into dive attributes table
				 */
				if (!($insertedok===true)) {
					// if updating, need to delete previous entries for this dive
					if ($editdivenumber > 0) {
						$deleted = $wpdb->delete($this->table_attributes_log, array('divenumber' => $editdivenumber));
						if ($deleted === false) {
							echo "<p>ERROR: Problem deleting dive type records for edited dive</p>";
						}
					}
					foreach ($this->get_attribute_ids() as $attrib_id) {
						$keyval = "attrib_" . $attrib_id;
						if (array_key_exists($keyval, $_REQUEST)) {
							$sqlvalues = array();
							$sqlvalues['divenumber'] = $divenumber;
							$sqlvalues['typeid'] = $attrib_id;
							$result = $wpdb->insert($this->table_attributes_log, $sqlvalues);
							if (!$result) {
								echo "<p>Problem inserting into: " . $this->table_attributes_log . "</p>";
							} 
						}
					}
				}
			}
		}

		/*
		 * Display Summary at top of page
		 */		 
		echo "<H1>Scuba Logger </H1>" . PHP_EOL;
		echo "<p>Use this page to create, modify and delete records of scuba dives. " . PHP_EOL;
		echo "For more information, and to report issues, visit the <a href=\"http://www.am-process.org/scuba/?page_id=974\">Scuba Logger webpage</a>.";
		$dive_count = $this->get_num_dives();
		echo "<p><b>Log summary:</b> Total number of dives is <b>" . $dive_count . "</b> (<b>" . $this->get_num_logged_dives() . "</b> logged); total time underwater is <b>" . $this->get_total_time_underwater() . "</b> minutes.</p>";
		echo "<p>To change length/depth or temperature units (e.g. from metres to feet or vice versa), go to Settings -> Scuba Logger.</p>" . PHP_EOL;

		/*
		 * If bad form was submitted, need to display errors.
		 */
		if (!$ok) {
			echo "<div id=\"formerrors\">";
			echo "<H2 class=\"rederror\">Dive not saved!</H2>" . PHP_EOL;
			echo "<p>Form entry had the following errors:</p>"; 
			echo "<ol>";
			foreach ($formErrors as $error) {
				echo "<li>" . $error . "</li>";
			}
			echo "</ol>";
			echo "<p>Please correct and re-submit.</p>"; 
			echo "</div>";
		}

		/*
		 * Display the form for adding/editing dives
		 */
		if ($doprefill) {
			$this->display_dive_form($prefillvalues, $editing);
		} else {
			$prefillvalues = array();
			foreach ($this->dive_descriptors as $key => $value) {
				$prefillvalues[$key] = NULL;
			}
			$this->display_dive_form($prefillvalues, $editing);
		}

		/*
		 * Display a summary of the dives logged
		 */
		echo "<table class=\"divelist\">" . PHP_EOL;
		echo "<tr class=\"divelisthead1\">" . PHP_EOL;
		echo "<td colspan=\"2\">&nbsp;</td>" . PHP_EOL;
		echo "<td class=\"divelisthead1\" colspan=\"5\" align=\"center\"><span><H3 class=\"divelisthead1\">List of Dives</H3></td>" . PHP_EOL;
		echo "<td class=\"divelisthead1\"><a href=\"javascript:{}\" class=\"showalllink\" id=\"showall\">show all</a></td>" . PHP_EOL;
		echo "<td class=\"divelisthead1\"><a href=\"javascript:{}\" class=\"showalllink\" id=\"hideall\">hide all</a></td>" . PHP_EOL;
		echo "</tr>". PHP_EOL;

		echo "<tr class=\"divelisthead2\">" . PHP_EOL;
		echo "<td class=\"divelist\"><b>Dive #</b></td>" . PHP_EOL;
		echo "<td class=\"divelist\"><b>Date</b></td>" . PHP_EOL;
		echo "<td class=\"divelist\"><b>Site</b></td>" . PHP_EOL;
		echo "<td class=\"divelist\"><b>Location</b></td>" . PHP_EOL;
		echo "<td class=\"divelist\"><b>Max. Depth</b></td>" . PHP_EOL;
		echo "<td class=\"divelist\"><b>Dive Time</b></td>" . PHP_EOL;
		echo "<td class=\"divelist\">&nbsp;</td>" . PHP_EOL;
		echo "<td class=\"divelist\">&nbsp;</td>" . PHP_EOL;
		echo "<td class=\"divelist\">&nbsp;</td>" . PHP_EOL;
		echo "</tr>" . PHP_EOL;

		$sql = "SELECT divenumber FROM $this->table_main_log";
		$divenumbers = $wpdb->get_col($sql);
		$countrows = 0;
		
		if (count($divenumbers) == 0) {
			echo "<tr class=\"divelisthead1\"><td class=\"divelisthead1\" colspan=\"9\" align=\"center\"><br/><emph>Dives will appear here...</emph><br/>&nbsp;</td></tr>";
		} else {

		foreach ($divenumbers as $i) {
			$countrows = $countrows + 1;
			$sql = "SELECT * FROM $this->table_main_log WHERE divenumber=$i";
			$result = $wpdb->get_row($sql);
			if ($countrows % 2 === 0) {
				echo "<tr class=\"divelist\">" . PHP_EOL;
			} else {
				echo "<tr class=\"alt\">" . PHP_EOL;
			}
			echo "<td class=\"divelist\">" . $this->sql_string_to_html($result->divenumber) . "</td>" . PHP_EOL;
			echo "<td class=\"divelist\">" . $this->sql_string_to_html($result->divedate)   . "</td>" . PHP_EOL;
			echo "<td class=\"divelist\">" . $this->sql_string_to_html($result->sitename)   . "</td>" . PHP_EOL;
			echo "<td class=\"divelist\">" . $this->sql_string_to_html($result->location)   . "</td>" . PHP_EOL;
			echo "<td class=\"divelist\">" . $this->sql_string_to_html($result->maxdepth)   . "</td>" . PHP_EOL;
			echo "<td class=\"divelist\">" . $this->sql_string_to_html($result->divetime)   . "</td>" . PHP_EOL;

			echo "<td class=\"divelist\">" . PHP_EOL;
			$url = admin_url('tools.php?page=ScubaLoggerToolsMenu');
			$formname = "deletediveform" . $i;
			echo "<form method=\"post\" action=\"$url\" id=\"" . $formname . "\">" . PHP_EOL;
			wp_nonce_field('delete_dive','delete_dive_nonce_field');
			echo "<input type=\"hidden\" name=\"scubaLoggerAction\" value=\"deleteDive\" />" . PHP_EOL;
			echo "<input type=\"hidden\" name=\"divenumber\" value=\"" . $i . "\" />" . PHP_EOL;
			$message = "Really delete dive " . $this->sql_string_to_html($result->divenumber) . "?";
			echo "<a class=\"divelistactionlink\" href=\"javascript:{}\" onclick=\"var r = confirm('" . $message . "'); if (r==true) {document.getElementById('" . $formname . "').submit();}\">delete</a>" . PHP_EOL;
			echo "</form>" . PHP_EOL;
			echo "</td>" . PHP_EOL;

			echo "<td class=\"divelist\">" . PHP_EOL;
			$url = admin_url('tools.php?page=ScubaLoggerToolsMenu');
			$formname = "editdiveform" . $i;
			echo "<form method=\"post\" action=\"$url\" id=\"" . $formname . "\">" . PHP_EOL;
			wp_nonce_field('edit_dive','edit_dive_nonce_field');
			echo "<input type=\"hidden\" name=\"scubaLoggerAction\" value=\"editDive\" />" . PHP_EOL;
			echo "<input type=\"hidden\" name=\"divenumber\" value=\"" . $i . "\" />" . PHP_EOL;
			echo "<a class=\"divelistactionlink\" href=\"javascript:{}\" onclick=\"document.getElementById('" . $formname . "').submit();\">edit</a>" . PHP_EOL;
			echo "</form>" . PHP_EOL;
			echo "</td>" . PHP_EOL;

			$hidelinkname = "hidelink_" . $i;
			$showlinkname = "showlink_" . $i;
			$rowname = "visrow_" . $i;
			echo "<td class=\"divelist\">" . PHP_EOL;
			echo "<a href=\"javascript:{}\" class=\"showlink\" id=\"" . $showlinkname . "\">show</a>" . PHP_EOL;
			echo "</td>" . PHP_EOL;
			echo "</tr>" . PHP_EOL;

			echo "<tr class=\"details\" id=\"" . $rowname . "\">";
			if ($countrows % 2 === 0) {
				echo "<td class=\"divelistfiller\">&nbsp;</td><td colspan=\"8\">";
			} else {
				echo "<td class=\"altfiller\">&nbsp;</td><td colspan=\"8\">";
			}

			echo "<table class=\"divedetails\">" . PHP_EOL;

			echo "<tr>";
			echo "<td class=\"divedetails\"><b>Objective:</b></td>";
			echo "<td class=\"divedetails\" colspan=\"5\">" . $this->sql_string_to_html($result->objective) . "</td>";
			echo "</tr>";
			
			echo "<tr>";
			echo "<td class=\"divedetails\"><b>Buddy:</b></td>";
			echo "<td class=\"divedetails\" colspan=\"5\">" . $this->sql_string_to_html($result->buddy) . "</td>";
			echo "</tr>";

			echo "<tr>";
			echo "<td class=\"divedetails\"><b>Dive attributes:</b></td>";
			echo "<td class=\"divedetails\" colspan=\"5\">";

			$dive_attribs = $this->get_dive_attributes($i);
			for ($j = 0; $j < count($dive_attribs); $j++) {
				echo $dive_attribs[$j];
				if ($j < count($dive_attribs) - 1) {
					echo ", ";
				}
			}
			echo "</td>";

			echo "</tr>";
			echo "<tr>";
			echo "<td class=\"divedetails\"><b>Time Down:</b></td>";
			echo "<td class=\"divedetails\" colspan=\"1\">" . $this->sql_string_to_html($result->timedown) . "</td>";
			echo "<td class=\"divedetails\"><b>Visibility (" . $this->get_depth_unit_abbrev() . "):</b></td><td class=\"divedetails\" colspan=\"1\">" . $this->sql_string_to_html($result->visibility) . "</td>";
			echo "<td class=\"divedetails\"><b>Boat name:</b></td><td class=\"divedetails\" colspan=\"1\">" . $this->sql_string_to_html($result->boatname) . "</td>";
			echo "</tr>";

			echo "<tr>";
			echo "<td class=\"divedetails\"><b>Av. Depth (" . $this->get_depth_unit_abbrev() . "):</b></td><td class=\"divedetails\">"  . $this->sql_string_to_html($result->avdepth)   . "</td>";
			echo "<td class=\"divedetails\"><b>Water Temp (" . $this->get_temp_unit_abbrev() . "):</b></td><td class=\"divedetails\">" . $this->sql_string_to_html($result->watertemp) . "</td>";
			echo "<td class=\"divedetails\"><b>Air Temp (" . $this->get_temp_unit_abbrev() . "):</b></td><td class=\"divedetails\">"   . $this->sql_string_to_html($result->airtemp)   . "</td>";
			echo "</tr>";

			echo "<tr>";
			echo "<td class=\"divedetails\"><b>Weather:</b></td><td class=\"divedetails\" colspan=\"1\">"        . $this->sql_string_to_html($result->weather)       . "</td>";
			echo "<td class=\"divedetails\"><b>Sea Conditions:</b></td><td class=\"divedetails\" colspan=\"1\">" . $this->sql_string_to_html($result->seaconditions) . "</td>";
			echo "<td class=\"divedetails\">&nbsp;</td><td class=\"divedetails\">&nbsp;</td>";
			echo "</tr>";
			
			echo "<tr>";
			echo "<td class=\"divedetails\"><b>Notes:</b></td><td class=\"divedetails\" colspan=\"5\">" . $this->sql_string_to_html($result->notes) . "</td>";
			echo "</tr>";

			echo "</table>" . PHP_EOL;

			echo "</td></tr>" . PHP_EOL;
		}
		}
		echo "</table>";
	}

	/*
	 * Display the form to add/edit dives
	 *
	 * $prefill - array of values with which to prefill form if editing
	 * $editing - dive number of dive we are editing
	 */
	public function display_dive_form($prefill, $editing) {
		global $wpdb;
		$url = admin_url('tools.php?page=ScubaLoggerToolsMenu');
		echo "<p>";
		echo "</p>";
		echo "<br/>";
		echo "<form method=\"post\" action=\"$url\">";
		wp_nonce_field('submit_dive_form','dive_form_nonce_field');
		echo "<input type=\"hidden\" name=\"scubaLoggerAction\" value=\"addDive\" />";
		echo "<input type=\"hidden\" name=\"editdivenumber\" value=\"" . $editing . "\" />";

?>

<table class="diveentry">
<tr>
<td class="diveentry" colspan="8" align="center"><H3 class="diveform">Dive Entry Form</H3></td>
</tr>
<tr>
<td class="diveentry" align="right">Dive Number:</td>

<?php

		if ($prefill['divenumber'] != NULL) {
			$numentry = $prefill['divenumber'];
		} else {
			$numentry = $this -> get_num_dives() + 1;
		}
		
?>

<td class="diveentry" colspan="2"><input type="number" class="formentry" name="divenumber" value="<?php echo "$numentry"; ?>" <?php if ($editing > 0) {echo "readonly=\"readonly\""; } ?> ></td>
<td class="diveentry" align="right">Date:</td>
<td class="diveentry" colspan="4"><input type="date" class="formentry" name="divedate" value="<?php echo $this -> sql_string_to_html($prefill['divedate']); ?>"></td>
</tr>
<tr>
<td class="diveentry" align="right">Dive Site:</td>
<td class="diveentry" colspan="2"><input type="text" class="formentry" name="sitename" size="30" value="<?php echo $this -> sql_string_to_html($prefill['sitename']); ?>"></td>
<td class="diveentry" align="right">Location:</td>
<td class="diveentry" colspan="4"><input type="text" class="formentry" name="location" size="30" value="<?php echo $this -> sql_string_to_html($prefill['location']); ?>"></td>
</tr>
<tr>
<td class="diveentry" align="right">Objective:</td>
<td class="diveentry" colspan="4"><input type="text" class="formentry" name="objective" size="55" value="<?php echo $this -> sql_string_to_html($prefill['objective']); ?>"></td>
<td class="diveentry" align="right">Buddy:</td>
<td class="diveentry" colspan="2"><input type="text" class="formentry" name="buddy" size="25" value="<?php echo $this -> sql_string_to_html($prefill['buddy']); ?>"></td>
</tr>
<tr>
<td class="diveentry" align="right">Time Down:</td>
<td class="diveentry"><input type="time" class="formentry" name="timedown" value="<?php echo $this -> sql_string_to_html($prefill['timedown']); ?>"></td>
<td class="diveentry" align="right">Dive Time (mins):</td>
<td class="diveentry"><input type="text" class="formentry" name="divetime" size="4" value="<?php echo $this -> sql_string_to_html($prefill['divetime']); ?>"></td>
<td class="diveentry" align="right">Max Depth (<?php echo $this->get_depth_unit_abbrev(); ?>):</td>
<td class="diveentry"><input type="text" class="formentry" name="maxdepth" size="4" value="<?php echo $this -> sql_string_to_html($prefill['maxdepth']); ?>"></td>
<td class="diveentry" align="right">Average Depth (<?php echo $this->get_depth_unit_abbrev(); ?>):</td>
<td class="diveentry"><input type="text" class="formentry" name="avdepth" size="4" value="<?php echo $this -> sql_string_to_html($prefill['avdepth']); ?>"></td>
</tr>
<tr>
<td class="diveentry" align="right">Water Temp (<?php echo $this->get_temp_unit_abbrev(); ?>):</td>
<td class="diveentry" colspan="2"><input type="text" class="formentry" name="watertemp" size="4" value="<?php echo $this -> sql_string_to_html($prefill['watertemp']); ?>"></td>
<td class="diveentry" align="right">Visibility (<?php echo $this->get_depth_unit_abbrev(); ?>):</td>
<td class="diveentry" colspan="1"><input type="text" class="formentry" name="visibility" size="4" value="<?php echo $this -> sql_string_to_html($prefill['visibility']); ?>"></td>
<td class="diveentry" align="right">Boat Name:</td>
<td class="diveentry" colspan="2"><input type="text" class="formentry" name="boatname" size="25" value="<?php echo $this -> sql_string_to_html($prefill['boatname']); ?>"></td>

</tr>
<tr>
<td class="diveentry" align="right">Air Temp (<?php echo $this->get_temp_unit_abbrev(); ?>):</td>
<td class="diveentry" colspan="2"><input type="text" class="formentry" name="airtemp" size="4" value="<?php echo $this -> sql_string_to_html($prefill['airtemp']); ?>"></td>
<td class="diveentry" align="right">Weather:</td>
<td class="diveentry"><input type="text" class="formentry" name="weather" value="<?php echo $this -> sql_string_to_html($prefill['weather']); ?>"></td>
<td class="diveentry" align="right">Sea Conditions:</td>
<td class="diveentry" colspan="2"><input type="text" class="formentry" name="seaconditions" size="25" value="<?php echo $this -> sql_string_to_html($prefill['seaconditions']); ?>"></td>
</tr>
<tr>

<?php 
 		/*
  		 * Do the attributes
  		 */
		$numCols = 8;
		$attrib_ids = $this->get_attribute_ids();
		for ($i = 0; $i < count($attrib_ids); $i++) {
			echo "<td class=\"diveentry\" align=\"right\">" . $this->map_id_to_attribute($attrib_ids[$i]) . ":</td><td class=\"diveentry\" align=\"center\"><input type=\"checkbox\" name=\"attrib_" . $attrib_ids[$i] . "\"";
			if (array_key_exists("attrib_" . $attrib_ids[$i], $prefill)) {
				echo " checked=\"checked\"";
			}
			echo "></td>";
			if ($i != 0 && ($i + 1) % ($numCols / 2) == 0) {
				echo "</tr><tr>";
			}
		}
		// Need to fill in the unused columns in this row of the table
		$numBlanks = $numCols - ((count($attrib_ids) * 2) % $numCols);
		if ($numBlanks > 0 && $numBlanks < $numCols) {
			echo "<td class=\"diveentry\" colspan=\"" . $numBlanks . "\">&nbsp;</td>";
		}
?>

</tr>
<tr>
<td class="diveentry" align="right">Notes:</td>
<td class="diveentry" colspan="7"><input type="text" class="formentry" name="notes" size="75" value="<?php echo $this -> sql_string_to_html($prefill['notes']); ?>"></td>
</tr>
<tr>
<td class="divesubmit" colspan="8" align="center"><input type="submit" value="Save Dive" class="submitbutton"></td>
</tr>
</table>

<?php
		echo "</form>";
		echo "<br/>";
	}

	/*
	 * Handle Short codes
	 */
	public function handle_shortcode($atts) {
		if (!array_key_exists('type', $atts)) {
			return "<b>Unable to parse scubalogger shortcode.</b>";
		}
		$output = "";
		switch ($atts['type']) {
			/*
			 * Output a table for a single dive
			 */
			case "dive":
				if (!array_key_exists('divenum', $atts) || !$this->is_good_integer($atts['divenum']) || !$this->divenumber_in_use(intval($atts['divenum']))) {
					$output = "Scubalogger: unable to execute dive shortcode. Attribute 'divenum' must be a valid dive number.";
					break;
				}
				$output = $this->generate_singledive_table_style_1(intval($atts['divenum']));
				break;
			/*
			 * Output a table for a double dive
			 */
			case "doubledive":
				if (!array_key_exists('divenum1', $atts) || !$this->is_good_integer($atts['divenum1']) || !$this->divenumber_in_use(intval($atts['divenum1']))) {
					$output = "Scubalogger: unable to execute doubledive shortcode. Attribute 'divenum1' must be a valid dive number.";
					break;
				}
				if (!array_key_exists('divenum2', $atts) || !$this->is_good_integer($atts['divenum2']) || !$this->divenumber_in_use(intval($atts['divenum2']))) {
					$output = "Scubalogger: unable to execute doubledive shortcode. Attribute 'divenum2' must bea valid dive number.";
					break;
				}
				$output = $this->generate_doubledive_table_style_1(intval($atts['divenum1']), intval($atts['divenum2']));
				break;
			/*
			 * Output a single descriptor of a particular dive.
			 */
			case "divedetail":
				if (!array_key_exists('divenum', $atts) || !$this->is_good_integer($atts['divenum']) || !$this->divenumber_in_use(intval($atts['divenum']))) {
					$output = "Scubalogger: unable to execute divedetail shortcode. Attribute 'divenum' must be a valid dive number.";
					break;
				}
				$output = $this->get_dive_detail(intval($atts['divenum']), $atts['detail']);
				break;
			/*
			 * Output a statistic of the dive log (e.g. number of dives)
			 */
			case "logstat":
				$format = "";
				if (array_key_exists('format', $atts)) {
					$format = $atts['format'];
				}
				$maxdivenum = null;
				if (array_key_exists('uptodive', $atts) && $this->is_good_integer($atts['uptodive']) && $this->divenumber_in_use(intval($atts['uptodive']))) {
					$maxdivenum = intval($atts['uptodive']);
				}
				$output = $this->get_log_statistic($atts['detail'], $format, $maxdivenum);
				break;
			/*
			 * Output a table summarising a selection of dives (with one dive per row)
			 */
			case "summarytable":
				if (!array_key_exists('divenums', $atts)) {
					$output = "Scubalogger: summary table is missing dive numbers. Cannot process shortcode.";
					break;
				}
				$divenums = explode(",", $atts['divenums'], 50);
				$numbers = array();
				foreach ($divenums as $divenum) {
					$divenum = trim($divenum);
					if ($this->is_good_integer($divenum) && $this->divenumber_in_use(intval($divenum))) {
						array_push($numbers, intval($divenum));		
					}
				}
				$output = $this->generate_dive_summaries_table($numbers);
				break;
			/*
			 * Output a query form
			 */
			case "querylogform":
				$output = $this->generate_query_page();
				break; 
			default:
				$output = "Cannot parse scubalogger shortcode.";
		}
		return $output;
	}
	
	/*
	 * Generate HTML table for a single dive (style 1)
	 *  - Assumes $divenmuber is a valid dive number.
	 */
	private function generate_singledive_table_style_1($divenumber) {
		$dive = $this->get_dive_details($divenumber);
		$output = "<table>" . PHP_EOL .
			"<tr>". PHP_EOL .
			"<td><strong>Place:</strong></td><td>" . $this->sql_string_to_html($dive['sitename']) . PHP_EOL .
			"</td><td><strong>Buddy:</strong></td><td>" . $this->sql_string_to_html($dive['buddy']) . "</td>" . PHP_EOL .
			"</tr><tr>" . PHP_EOL .
			"<td><strong>Weather:</strong></td><td>" . $this->sql_string_to_html($dive['weather']) . "</td>" . PHP_EOL;
		$dive_attribs = $this->get_dive_attributes($divenumber);
		if (in_array("Boat", $dive_attribs)) {
			$boat = "Boat";
			if (rtrim($dive['boatname']) !== '') {
				$boat .= " (<emph>" . $this->sql_string_to_html($dive['boatname']) . "</emph>)";
			}
			$output = $output . "<td><strong>Type:</strong></td><td>" . $boat . "</td>" . PHP_EOL;
		} else {
			$output = $output . "<td><strong>Type:</strong></td><td>Shore</td>" . PHP_EOL;
		}
		$output = $output . "</tr>" . PHP_EOL .
			"<tr>" . PHP_EOL .
			"<td><strong>Max. depth (" . $this->get_depth_unit_abbrev() . "):</strong></td><td>" . number_format($dive['maxdepth'],1) . "</td>" . PHP_EOL .
			"<td><strong>Visibility (" . $this->get_depth_unit_abbrev() . "):</strong></td><td>" . number_format($dive['visibility'],0) . "</td>" . PHP_EOL .
			"</tr>" . PHP_EOL .
			"<tr>" . PHP_EOL .
			"<td><strong>Time down:</strong></td><td>" . substr($dive['timedown'], 0, strlen($dive['timedown'])-3) . "</td>" . PHP_EOL .
			"<td><strong>Dive time (mins):</strong></td><td>" . $dive['divetime'] . "</td>" . PHP_EOL .
			"</tr>" . PHP_EOL .
			"<tr>" . PHP_EOL .
			"<td><strong>Average depth (" . $this->get_depth_unit_abbrev() . "):</strong></td><td>" . number_format($dive['avdepth'],1) . "</td>" . PHP_EOL .
			"<td><strong>Water temp. (" . $this->get_temp_unit_abbrev() . "):</strong></td><td>" . number_format($dive['watertemp'],1) . "</td>" . PHP_EOL .
			"</tr>" . PHP_EOL .
			"</table>" . PHP_EOL;
		return $output;	
	}
	
	/**
	 * Get a single dive detail, suitable for output in HTML.
	 *  - Assumes $divenumber is valid
	 */
	private function get_dive_detail($divenumber, $detail) {
		$dive = $this->get_dive_details($divenumber);
		if (!array_key_exists($detail, $dive)) {
			return "Invalid dive detail code";
		}		
		return $this->sql_string_to_html($dive[$detail]);
	}
	
	/*
	 * Return a particular summary statistic of the dive log.
	 */
	private function get_log_statistic($detail, $format, $maxdivenum) {
		if (strcmp($detail, "timeunderwater") == 0) {
			$nmins = $this->get_total_time_underwater($maxdivenum); 
			if (strcmp($format, "hm") == 0) {
				$hours = round(floor($nmins / 60));
				$mins = $nmins - $hours * 60;
				$hourstr = "hours";
				if ($hours == 1) {
					$hourstr = "hour";
				}
				$minutestr = "minutes";
				if ($mins == 1) {
					$minutestr = "minute";
				}
				$res = $hours . " " . $hourstr . " and " . $mins . " " . $minutestr;
			}
			elseif (strcmp($format, "dhm") == 0) {
				$days = round(floor($nmins / (60 * 24)));
				$nmins = $nmins - $days * 60 * 24;
				$hours = round(floor($nmins / 60));
				$mins = $nmins - $hours * 60;
				$daystr = "days";
				if ($days == 1) {
					$daystr = "day";
				}
				$hourstr = "hours";
				if ($hours == 1) {
					$hourstr = "hour";
				}
				$minutestr = "minutes";
				if ($mins == 1) {
					$minutestr = "minute";
				}

				$res = $days . " " . $daystr . ", " . $hours . " " . $hourstr . " and " . $mins . " " . $minutestr;
			}
			else {
				$res = "" . $nmins;
			}
			return $res;	
		}
		if (strcmp($detail, "numdives") == 0) {
			return $this->get_num_dives();
		}
		if (strcmp($detail, "numloggeddives") == 0) {
			return $this->get_num_logged_dives();
		}
		return "Error: unknown stat.";	
	}

	/**
	 * Generate a html table for a double dive (style 1)
	 */
	private function generate_doubledive_table_style_1($divenumber1, $divenumber2) {
		$dive1 = $this->get_dive_details($divenumber1);
		$dive2 = $this->get_dive_details($divenumber2);
		$output = "<table>" . PHP_EOL .
			"<tr>" . PHP_EOL .
			"<td>&nbsp;</td><td><strong>Dive 1</strong></td><td><strong>Dive 2</strong></td>" . PHP_EOL .
			"<td>&nbsp;</td><td><strong>Dive 1</strong></td><td><strong>Dive 2</strong></td>" . PHP_EOL .
			"</tr>" . PHP_EOL .
			"<tr>". PHP_EOL .
			"<td><strong>Place:</strong></td><td>" . $this->sql_string_to_html($dive1['sitename']) . "</td><td>" 
					. $this->sql_string_to_html($dive2['sitename']) . "</td>" . PHP_EOL;
			if ($dive1['buddy'] === $dive2['buddy']) {
				$output = $output . "<td><strong>Buddy:</strong></td><td colspan='2'>" . 
					$this->sql_string_to_html($dive1['buddy']) . "</td>" . PHP_EOL;
			} else {
				$output = $output . "<td><strong>Buddy:</strong></td><td>" . $this->sql_string_to_html($dive1['buddy']) . 
					"</td><td>" . $this->sql_string_to_html($dive2['buddy']) . "</td>" . PHP_EOL;
			}
			$output = $output . "</tr>" . PHP_EOL .
			"<tr>" . PHP_EOL .
			"<td><strong>Weather:</strong></td><td colspan='2'>" . $this->sql_string_to_html($dive1['weather']) . "</td>" . PHP_EOL;
			$dive1_attribs = $this->get_dive_attributes($divenumber1);
			if (in_array("Boat", $dive1_attribs)) {
				$boat = "Boat";
				if (rtrim($dive1['boatname']) !== '') {
					$boat .= " (<emph>" . $this->sql_string_to_html($dive1['boatname']) . "</emph>)";
				}
				$output = $output . "<td><strong>Type:</strong></td><td colspan='2''>" . $boat . "</td>" . PHP_EOL;
			} else {
				$output = $output . "<td><strong>Type:</strong></td><td colspan='2''>Shore</emph></td>" . PHP_EOL;
			}
			$output = $output . "</tr>" . PHP_EOL .
			"<tr>" . PHP_EOL .
			"<td><strong>Max. depth (" . $this->get_depth_unit_abbrev() . "):</strong></td><td>" . number_format($dive1['maxdepth'],1) . "</td><td>" . number_format($dive2['maxdepth'],1) . "</td>" . PHP_EOL .
			"<td><strong>Visibility (" . $this->get_depth_unit_abbrev() . "):</strong></td><td>" . number_format($dive1['visibility'],0) . "</td><td>" . number_format($dive2['visibility'],0) . "</td>" . PHP_EOL .
			"</tr>" . PHP_EOL .
			"<tr>" . PHP_EOL .
			"<td><strong>Time down:</strong></td><td>" . substr($dive1['timedown'], 0, strlen($dive1['timedown'])-3) . 
					"</td><td>" . substr($dive2['timedown'], 0, strlen($dive2['timedown'])-3) . "</td>" . PHP_EOL .
			"<td><strong>Dive time (mins):</strong></td><td>" . $dive1['divetime'] . "</td><td>" . $dive2['divetime'] . "</td>" . PHP_EOL .
			"</tr>" . PHP_EOL .
			"<tr>" . PHP_EOL .
			"<td><strong>Average depth (" . $this->get_depth_unit_abbrev() . "):</strong></td><td>" . number_format($dive1['avdepth'],1) . "</td><td>" . number_format($dive2['avdepth'],1) . "</td>" . PHP_EOL .
			"<td><strong>Water temp. (" . $this->get_temp_unit_abbrev() . "):</strong></td><td>" . number_format($dive1['watertemp'],1) . "</td><td>" . number_format($dive2['watertemp'],1) . "</td>" . PHP_EOL .
			"</tr>" . PHP_EOL .
			"</table>" . PHP_EOL;
		return $output;
	}
	
	/**
	 * Generate a html table of dive summaries - 1 row per dive.
	 *  - $divenums is array af valid dive numbers.
	 */
	private function generate_dive_summaries_table($divenums) {
		$output = "";
		$output .= "<table>" . PHP_EOL;
		$output .= "<tr>" . PHP_EOL;
		$output .= "<td><b>Dive #</b></td>" . PHP_EOL;
		$output .= "<td><b>Date</b></td>" . PHP_EOL;
		$output .= "<td><b>Time Down</b></td>" . PHP_EOL;
		$output .= "<td><b>Dive Time (mins)</b></td>" . PHP_EOL;
		$output .= "<td><b>Max Depth (" . $this->get_depth_unit_abbrev() . ")</b></td>" . PHP_EOL;
		$output .= "<td><b>Average Depth (" . $this->get_depth_unit_abbrev() . ")</b></td>" . PHP_EOL;
		$output .= "<td><b>Water Temp. (" . $this->get_temp_unit_abbrev() . ")</b></td>" . PHP_EOL;
		$output .= "<td><b>Objective -- Notes</b></td>" . PHP_EOL;
		$output .= "</tr>" . PHP_EOL;
		foreach($divenums as $divenum) {
			$dive = $this->get_dive_details($divenum);
			$output .= "<tr>" . PHP_EOL;
			$output .= "<td>" . $dive['divenumber'] . "</td>" . PHP_EOL;
			$output .= "<td>" . $dive['divedate'] . "</td>" . PHP_EOL;
			$output .= "<td>" . $dive['timedown'] . "</td>" . PHP_EOL;
			$output .= "<td>" . $dive['divetime'] . "</td>" . PHP_EOL;
			$output .= "<td>" . number_format($dive['maxdepth'],1) . "</td>" . PHP_EOL;
			$output .= "<td>" . number_format($dive['avdepth'],1) . "</td>" . PHP_EOL;
			$output .= "<td>" . number_format($dive['watertemp'],1) . "</td>" . PHP_EOL;
			$output .= "<td>" . $this->sql_string_to_html($dive['objective']) . " -- " 
				. $this->sql_string_to_html($dive['notes']) . "</td>" . PHP_EOL;
			$output .= "</tr>" . PHP_EOL;
		}
		$output .= "</table>" . PHP_EOL;
		return $output;
	}
	
	/**
	 * Generate a HTML form for querying the dive log.
	 */
	private function generate_query_page() {
		global $wpdb;
		$output = "";
		$ok = false;
		$formErrors = array();
		
		wp_enqueue_style('scubalogger-css', plugins_url('scubalogger.css', __FILE__));
		
		/*
		 * If query form has been submitted, we should show teh results
		 */
		if (isset($_POST['query_form_nonce_field']) && wp_verify_nonce($_POST['query_form_nonce_field'],'query_log')) {
			
			// Verify numerical query parameters and construct query
			$ok = true;
			$sql = "SELECT * FROM " . $this->table_main_log . " WHERE divenumber > 0";	
			if ($_REQUEST['maxdepthmin'] != "") {
				if ($this->is_good_float($_REQUEST['maxdepthmin'])) {
					$sql .= " AND maxdepth >= " . floatval($_REQUEST['maxdepthmin']);
				} else {
					$ok = false;
					array_push($formErrors, "Minimum Max Depth must be blank or numeric.");
				}
			} 
			if ($_REQUEST['maxdepthmax'] != "") {
				if ($this->is_good_float($_REQUEST['maxdepthmax'])) {
					$sql .= " AND maxdepth <= " . floatval($_REQUEST['maxdepthmax']);
				} else {
					$ok = false;
					array_push($formErrors, "Maximim Max Depth must be blank or numeric.");
				}
			} 
			if ($_REQUEST['mindate'] != "") {
				if ($this->is_good_date($_REQUEST['mindate'])) {
					$sql .= " AND divedate >= '" . $_REQUEST['mindate'] . "'";
				} else {
					$ok = false;
					array_push($formErrors, "Earliest Date must be YYYY-MM-DD.");
				}
			}
			if ($_REQUEST['maxdate'] != "") {
				if ($this->is_good_date($_REQUEST['maxdate'])) {
					$sql .= " AND divedate <= '" . $_REQUEST['maxdate'] . "'";
				} else {
					$ok = false;
					array_push($formErrors, "Latest Date must be YYYY-MM-DD.");
				}
			}
			
			if ($ok) {
				// Query database with all numeric parameters
				$dive_records = $wpdb->get_results($sql, ARRAY_A);
			
				// Remove from search results those that do not match text parameters (case is ignored)
				if ($_REQUEST['sitenamecontains'] != "") {
					$sitenamecontains = $_REQUEST['sitenamecontains'];
					foreach($dive_records as $divekey => $dive_record) {
						if (preg_match("/" . preg_quote(strtolower($sitenamecontains)) . "/", strtolower($dive_record['sitename'])) != 1){
							unset($dive_records[$divekey]);
						}
					}
				}
				if ($_REQUEST['buddynamecontains'] != "") {
					$buddynamecontains = $_REQUEST['buddynamecontains'];
					foreach($dive_records as $divekey => $dive_record) {
						if (preg_match("/" . preg_quote(strtolower($buddynamecontains)) . "/", strtolower($dive_record['buddy'])) != 1){
							unset($dive_records[$divekey]);
						}
					}
				}
				// Remove from search results those that do not have spec'd attributes.
				foreach ($this->get_attribute_ids() as $attrib_id) {
					if (array_key_exists("attrib_" . $attrib_id, $_REQUEST)) {
						foreach($dive_records as $divekey => $dive_record) {
							if (! $this->has_dive_attribute($dive_record['divenumber'], $attrib_id)) {
								unset($dive_records[$divekey]);
							}
						}	
					}
				}
			
				$output .= "<H3>Search Results</H3>";
			
				// Output results
				$output .= "<p>Number of dives matching the search criteria: " . count($dive_records) . "</p>";
				$output .= "<table>";
				$output .= "<tr><td><b>#</b></td><td><b>Date</b></td><td><b>Site</b></td><td><b>Relevant Posts</b></td></tr>";
				foreach($dive_records as $dive_record) {
					$output .= "<tr>";
					$output .= "<td>";
					$output .= $dive_record['divenumber'];
					$output .= "</td>";
					$output .= "<td>";
					$output .= $dive_record['divedate'];
					$output .= "</td>";
					$output .= "<td>";
					$output .= $this->sql_string_to_html($dive_record['sitename']);
					$output .= "</td>";
					$output .= "<td>";
					$permalinks = $this->find_posts_about_dive($dive_record['divenumber']);
					if (count($permalinks) == 0) {
						$output .= "Not referenced in any posts";
					} else {
						for ($i = 0; $i < count($permalinks); $i++) {
							$output .= "<a href=\"" . $permalinks[$i] . "\">Post " . $i . "</a> ";
						}
					}
					$output .= "</td>";
					$output .= "</tr>";
				}
				$output .= "</table>";
				$url = get_permalink();
				$output .= "<br /><p><a href=\"" . $url . "\">New Search</a></p>";
			}
		}
		
		if (!$ok) {
			/*
		 	 * Show the query form
		 	 */ 
		 	 if (count($formErrors) > 0) {
		 		$output .= "<div id=\"formerrors\">";
				$output .= "<H2 class=\"rederror\">Erroneous Query!</H2>" . PHP_EOL;
				$output .= "<p>Form entry had the following errors:</p>"; 
				$output .= "<ol>";
				foreach ($formErrors as $error) {
					$output .= "<li>" . $error . "</li>";
				}
				$output .= "</ol>";
				$output .= "</div>";
				$output .= "<p>&nbsp;</p>";
			}
		 	 
			$url = $this->current_page_url();
			$output .= "<table class=\"queryform\">" . PHP_EOL;
			$output .= "<form method=\"post\" action=\"" . $url . "\">". PHP_EOL . wp_nonce_field('query_log','query_form_nonce_field') . PHP_EOL;
			$output .= "<input type=\"hidden\" name=\"scubaLoggerAction\" value=\"querylog\" />" . PHP_EOL;
			$output .= "<tr><td class=\"queryform\" colspan=\"4\"><H3>Enter Search Parameters</H3><br /><emph>(Leave blank as necessary)<emph></td></tr>" . PHP_EOL;
			$output .= "<tr><td class=\"queryform\"><b>Site name contains:</b></td><td colspan=\"3\"><input type=\"text\" name=\"sitenamecontains\" class=\"queryforminput\" size=\"40\"></td></tr>" . PHP_EOL;
			$output .= "<tr><td class=\"queryform\"><b>Buddy name contains:</b></td><td colspan=\"3\"><input type=\"text\" name=\"buddynamecontains\" class=\"queryforminput\" size=\"40\"></td></tr>" . PHP_EOL;
			$output .= "<tr><td class=\"queryform\"><b>Date Between:</b></td><td><input type=\"date\" name=\"mindate\" class=\"queryforminput\"></td><td><b>and</b></td>" . "<td><input type=\"date\" name=\"maxdate\" class=\"queryforminput\"></td></tr>" . PHP_EOL;
			$output .= "<tr><td class=\"queryform\"><b>Max depth between:</b></td><td><input type=\"number\" name=\"maxdepthmin\" class=\"queryforminput\" size=\"5\"></td>" . "<td><b>and</b></td><td><input type=\"number\" name=\"maxdepthmax\" class=\"queryforminput\" size=\"5\"></td></tr>" . PHP_EOL;
		
			$output .= "<tr>";
			$numCols = 4;
			$attrib_ids = $this->get_attribute_ids();
			for ($i = 0; $i < count($attrib_ids); $i++) {
				$attrib_id = $attrib_ids[$i];
				$output .= "<td class=\"queryform\" align=\"right\"><b>" . $this->map_id_to_attribute($attrib_id) . ":</b></td><td class=\"queryform\" align=\"center\"><input type=\"checkbox\" name=\"attrib_" . $attrib_id . "\"";
				$output .= "></td>";
				if ($i != 0 && ($i + 1) % ($numCols / 2) == 0) {
					$output .= "</tr><tr>";
				}
			}
			$numBlanks = $numCols - ((count($attrib_ids) * 2) % $numCols);
			if ($numBlanks > 0 && $numBlanks < $numCols) {
				$output .= "<td class=\"queryform\" colspan=\"" . $numBlanks . "\">&nbsp;</td>";
			}
			$output .= "</tr>" . PHP_EOL;
			$output .= "<tr><td class=\"queryform\" colspan=\"4\"><input type=\"submit\" value=\"Query\"></td></tr>" . PHP_EOL;
			$output .= "</form>";
			$output .= "</table>";		
		}	
		return $output;
	}

	/*
	 * Return an array of permalinks to posts which include shortcodes that reference a particular dive number.
	 */
	private function find_posts_about_dive($divenumber) {
		
		$permalinks = array(); // this will be returned full of links to posts which include shortcodes that reference the dive in question
		$count_posts = wp_count_posts();
		$posts_to_search = $count_posts->publish;
		$total_searched = 0;
		$batch_size = 1; // number of posts to search per iteration
		
		$args = array(
			'posts_per_page'   => $batch_size,
			'category'         => '',
			'orderby'          => 'post_date',
			'order'            => 'DESC',
			'include'          => '',
			'exclude'          => '',
			'meta_key'         => '',
			'meta_value'       => '',
			'post_type'        => 'post',
			'post_mime_type'   => '',
			'post_parent'      => '',
			'post_status'      => 'publish',
			'suppress_filters' => true 
		);
		
		// Regular expressions for matching shortcodes that involve the divenumber in question
		$pattern1 = "/\[scubalogger[^\]]+divenum[1-2]?\s*=\s*\"\s*[0]*" . $divenumber . "\s*\"/";   // "X"
		$pattern2 = "/\[scubalogger[^\]]+divenums\s*=\s*\"[^\"]+,\s*[0]*" . $divenumber . "\s*,/";  // ,X,
		$pattern3 = "/\[scubalogger[^\]]+divenums\s*=\s*\"\s*[0]*" . $divenumber . "\s*,/";         // "X,
		$pattern4 = "/\[scubalogger[^\]]+divenums\s*=\s*\"[^\"]+,\s*[0]*" . $divenumber . "\s*\"/"; // ,X"
		
		while ($total_searched < $posts_to_search) {
			$args['offset'] = $total_searched;
			$myposts = get_posts($args);
			foreach ($myposts as $post) {
				setup_postdata($post);
				if (   preg_match($pattern1, $post->post_content) == 1 
					|| preg_match($pattern2, $post->post_content) == 1
					|| preg_match($pattern3, $post->post_content) == 1
					|| preg_match($pattern4, $post->post_content) == 1) 
				{
					array_push($permalinks, get_permalink($post->ID));
				}
			} 
			wp_reset_postdata();
			$total_searched += $batch_size;			
		}
		return $permalinks;
	}

	/*
	 * Check that a string can be properly converted to an integer with absolute value <= 100,000
	 */
	private function is_good_integer($str) {
		if (strlen($str) > 12) {
			return false;
		}
		if (preg_match('/\A[+-]{0,1}[0-9]+\z/', $str) != 1) {
			return false;
		}
		if (abs(intval($str)) > 100000) {
			return false;
		}
		return true;
	}

	/*
	 * Check that a string can be properly converted to a float with absolute value <= 100,000
	 */
	private function is_good_float($str) {
		if (strlen($str) > 12) {
			return false;
		}
		if (preg_match('/\A[+-]{0,1}[0-9]*[.]?[0-9]+\z/', $str) != 1) {
			return false;
		}
		if (abs(intval($str)) > 100000) {
			return false;
		}
		return true;
	}
	
	/*
	 * Check that a string can be properly converted to a date: yyyy-mm-dd
	 */
	private function is_good_date($str) {
		if (strlen($str) != 10) {
			return false;
		}
		if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $str) != 1) {
			return false;
		}
		if (intval(substr($str,5,2)) > 12) {
			return false;
		}
		if (intval(substr($str,8,2)) > 31) {
			return false;
		}
		return true;
	}
	
	/*
	 * Check if a string can be properly converted to a time: HH:MM or HH:MM:SS
	 * (Only checks the validity of HH and MM)
	 */
	private function is_good_time($str) {
		if (strlen($str) > 8) {
			return false;
		}
		if (! (preg_match('/\A[0-9]{1,2}:[0-9]{2}\z/', $str) == 1 || preg_match('/\A[0-9]{1,2}:[0-9]{2}:[0-9]{2}\z/', $str) == 1)) {
			return false;
		}
		$timevals = explode(':', $str);
		$hour = intval($timevals[0]);
		$minute = intval($timevals[1]);
		if ($hour < 0 || $hour > 23) {
			return false;
		}
		if ($minute < 0 || $minute > 59) {
			return false;
		}
		return true;
	}

	/*
	 * Convert an escaped string from an SQL command into a string ready for use in html,
	 * even in the 'value' field of a form, for example, where double inverted commas are not allowed.
	 */
	private function sql_string_to_html($sql_string) {
		$html_string = stripslashes_deep($sql_string);
		$html_string = str_replace("&",  "&amp;",  $html_string); // the & must come first or it messes up the rest!
		$html_string = str_replace("\"", "&quot;", $html_string);
		$html_string = str_replace("<",  "&lt;",   $html_string);
		$html_string = str_replace(">",  "&gt;",   $html_string);
		$html_string = str_replace("!",  "&#33;",  $html_string);
		$html_string = str_replace("'",  "&#39;",  $html_string);
		return $html_string;
	}
	
	/**
	 * Get the URL of the current page:
	 * From: http://wordpress.org/support/topic/current-page-url-1
	 */
	private function current_page_url() {
		$pageURL = 'http';
		if (isset($_SERVER["HTTPS"])) {
			if ($_SERVER["HTTPS"] == "on") {
				$pageURL .= "s";
			}
		}
		$pageURL .= "://";
		if ($_SERVER["SERVER_PORT"] != "80") {
			$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
		} else {
			$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
		}
		return $pageURL;	
	}
}
?>
