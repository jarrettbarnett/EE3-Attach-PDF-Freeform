<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD . 'jb_attachpdf/config.php';

class Jb_attachpdf_ext
{
    var $name = "JB Attach PDF";
    var $description = JB_ATTACHPDF_DESC;
    var $version = JB_ATTACHPDF_VERSION;
    var $settings_exist = 'n';
    var $docs_url = '';
    var $settings = array();
    var $pdf;
    var $upload_path;


    function __construct($settings = '')
    {
        // nothing for now
    }

    /**
     * Activate Extension
     */
    function activate_extension()
    {
        $hooks = array(
            'freeform_module_user_notification' => 'freeform_module_user_notification',
            'freeform_module_insert_end' => 'generate_pdf',
            'email_send' => 'email_send',
        );

        // activate hooks
        foreach ($hooks as $hook => $method)
        {
            $data = array(
                'class'    => __CLASS__,
                'method'   => $method, // method to trigger
                'hook'     => $hook, // hook that calls the method
                'settings' => serialize($this->settings),
                'priority' => 10,
                'version'  => JB_ATTACHPDF_VERSION,
                'enabled'  => 'y'
            );

            ee()->db->insert('extensions', $data);
        }

        // setup action
        $action_data = [
            'class' => 'Jb_attachpdf',
            'method' => 'generate_pdf'
        ];
        ee()->db->insert('actions', $action_data);
    }

    function build_pdf($template_path, $upload_field_settings)
    {
        $filename = 'custom-report-' . date('YmdHis') . '-' . rand(10000,9999999) . '.pdf';

        // generate pdf
        $pdf_template_path = $template_path;
        $settings = array(
            'orientation'   => 'portrait',
            'size'          => 'letter',
            'compress'      => '1',
            'encrypt'       => false,
            'filename'      => $filename,
            'cache_enabled' => false
        );
        //$pdf_output = ee()->extensions->call('pdf_press_generate_pdf', $pdf_template_path, $settings);

        // store pdf
        //file_put_contents(FCPATH . $upload_field_settings['upload_path'] . $filename, $pdf_output);
        $filename = 'custom-report-201701251429315726736.pdf';

        return $filename;
    }

    /**
     * Get Field Settings
     *
     * @param $field_name
     * @return array
     */
    function get_field_settings_for_upload_field($field_name)
    {
        $results = ee()->db->select('*')->from('freeform_fields')->where([
            'field_name' => $field_name
        ])->limit(1)->get();

        $settings = json_decode($results->row('settings'), true);

        $upload_results = ee()->db->select('*')->from('upload_prefs')->where([
            'id' => $settings['file_upload_location'],
            'site_id' => $results->row('site_id')
        ])->limit(1)->get();

        $upload_path = str_replace('{base_path}/', '', $upload_results->row('server_path'));

        $r = [];
        $r['field_id'] = $results->row('field_id');
        $r['field_name'] = $results->row('field_name');
        $r['site_id'] = $results->row('site_id');
        $r['upload_path'] = $upload_path;

        return $r;
    }

    /**
     * Generate PDF
     *
     * Generate and insert PDF Into FreeForm entry
     *
     * @param $field_input_data
     * @param $entry_id
     * @param $form_id
     * @param $freeform
     * @return mixed
     */
    function generate_pdf($field_input_data, $entry_id, $form_id, $freeform)
    {
        if (ee()->extensions->active_hook('pdf_press_generate_pdf') !== TRUE)
        {
            return $field_input_data;
        }

        // check for required pdf_press form fields
        // pdf_press_entries - delimited list of entry ids to report
        // pdf_press_template - template to fetch
        // pdf_press_upload_fieldname - file_upload FreeForm fieldname
        // pdf_press_filename_fieldname - filename string FreeForm fieldname
        if (empty($field_input_data['pdf_press_entries']) || empty($field_input_data['pdf_press_template']) || empty($field_input_data['pdf_press_upload_fieldname']) || empty($field_input_data['pdf_press_filename_fieldname']))
        {
            return $field_input_data;
        }

        // get upload field settings
        $upload_field_settings = $this->get_field_settings_for_upload_field($field_input_data['pdf_press_upload_fieldname']);

        // convert entries to utilize hyphens instead of pipes (since pipes are not allowed in segments)
        $entries = str_replace('|', '-', $field_input_data['pdf_press_entries']);
        $template_path = $field_input_data['pdf_press_template'] . '/' . $entries;

        // build pdf
        $pdf_file = $this->build_pdf($template_path, $upload_field_settings);

        // set attachments for FreeForm (even though FreeForm isn't doing anything actionable with it currently)
        $variables['attachments'][0] = ['fileurl' => '/'.$upload_field_settings['upload_path'] . $pdf_file, 'filename' => $pdf_file];
        $variables['attachment_count'] = 1;
        $variables['report'] = $variables['attachments'];

        // update specific form fields
        $variables[$field_input_data['pdf_press_upload_fieldname']] = '/'.$upload_field_settings['upload_path'] . $pdf_file;
        $variables[$field_input_data['pdf_press_filename_fieldname']] = '/'.$upload_field_settings['upload_path'] . $pdf_file;

        // insert attachment into database so FreeForm associates it with the entry
        $data = [
            'site_id' => $upload_field_settings['site_id'],
            'form_id' => $form_id,
            'entry_id' => $entry_id,
            'field_id' => $upload_field_settings['field_id'],
            'server_path' => FCPATH . $upload_field_settings['upload_path'],
            'filename' => $pdf_file,
            'extension' => 'pdf',
            'filesize' => filesize(FCPATH . $upload_field_settings['upload_path'] . $pdf_file)
        ];

        ee()->db->insert('freeform_file_uploads', $data);

        return $field_input_data;
    }

    /**
     * Send Email / Attach PDF (since FreeForm hooks are useless and currently unable to attach files via their hooks)
     *
     * @param $headers
     * @param $header_str
     * @param $recipients
     * @param $cc_array
     * @param $bcc_array
     * @param $subject
     * @param $finalbody
     * @return bool|void
     */
    function email_send($headers, $header_str, $recipients, $cc_array, $bcc_array, $subject, $finalbody)
    {
        if (ee()->extensions->active_hook('pdf_press_generate_pdf') !== TRUE)
        {
            return false;
        }

        // if email contains our report, attach it
        if (strpos($headers['finalbody'], '%%jb_attachpdf%%') > -1)
        {
            ee()->email->attach($this->pdf);
        }

        return;

        // take over emails ending
        //ee()->extensions->end_script = true;

        // return spool value
        //return true;
    }

    /**
     * Add Attachments To User Notification
     *
     * Updating FreeForm with attachment information, even though it doesnt do anything due to the hook being called TOO LATE
     * Maybe this will change in the future so I'll leave this here for good measure
     */
    function freeform_module_user_notification($field_labels, $entry_id, $variables, $form_id, $freeform)
    {
        if (ee()->extensions->active_hook('pdf_press_generate_pdf') !== TRUE)
        {
            return $variables;
        }

        // get attachments for freeform_id: $entry_id
        $results = ee()->db->select('*')->from('freeform_file_uploads')->where([
            'entry_id' => $entry_id
        ])->get();

        // stop here if no attachments exist for submission
        if ($results->num_rows() == 0)
        {
            return $variables;
        }

        // we use this to determine what emails to attach
        $variables['bcc_recipients'][] = 'pdf_press+'.$entry_id.'@jarrettbarnett.com';

//        $count = 0;
//        $field_input_data = $variables['field_inputs'];
//
//        foreach ($results->result_array() as $row)
//        {
//            // strip out document root so we only end up with relative upload path
//            $upload_path = '/' . str_replace(FCPATH, '', $row['server_path']);
//            $variables['attachments'][] = [
//                'fileurl' => $upload_path . $row['filename'],
//                'filename' => $row['filename']
//            ];
//            $count++;
//        }
//
//        $variables['attachment_count'] = $count;
//        $variables[$field_input_data['pdf_press_fieldname']] = $variables['attachments'];
//        $variables['pdf_press_filename_fieldname'] = '%%JB_ATTACHPDF%%' . $entry_id . '%%';

        return $variables;
    }

    /**
     * Updates the extension
     *
     * @param string $current
     * @return bool
     */
    function update_extension($current = '')
    {
        if ($current == '' OR $current == JB_ATTACHPDF_VERSION)
        {
            return false;
        }

        ee()->db->where('class', __CLASS__)
                ->update(
                    'extensions',
                    array('version' => JB_ATTACHPDF_VERSION)
                );
    }

    /**
     * Disables the Extension
     */
    function disable_extension()
    {
        ee()->db->where('class', __CLASS__)
                ->delete('extensions');
    }

    /**
     * Settings
     *
     * @return array
     */
    function settings()
    {
        $settings = array();

        // No settings at this time
        return $settings;
    }
}