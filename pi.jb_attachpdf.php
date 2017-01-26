<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
	'pi_name' => 'JB PDF Press Email',
	'pi_version' => '1.0',
	'pi_author' => 'Jarrett Barnett',
	'pi_author_url' => 'http://jarrettbarnett.com',
	'pi_description' => 'Links PDF Press to FreeForm.',
	'pi_usage' => '', // TODO

);



/**
 * PDF Press Email
 *
 * @author Jarrett Barnett
 *
 * @property CI_Controller $EE
 */
class Jb_attachpdf
{
	/**
	 * @var string the plugin result
	 */
	public $return_data = '';

    /**
     * Jb_pdf_press_email constructor.
     */
	public function __construct()
	{

	}

	public function get_entries()
    {
        $tagdata = ee()->TMPL->tagdata;
        $entries = ee()->TMPL->fetch_param('entries');
        $prefix = ee()->TMPL->fetch_param('prefix', 'jb');
        $entries = str_replace('-', '|', $entries);
        $variables[] = [
            $prefix.':entries' => $entries
        ];
        $this->return_data = ee()->TMPL->parse_variables($tagdata, $variables);
    }

    public function generate_pdf()
    {
        if (ee()->extensions->active_hook('pdf_press_generate_pdf') !== TRUE) {
            return ee()->TMPL->tagdata;
        }

        // get parameters


        $field_input_data = $variables['field_inputs'];

        // pdf_press fields submitted?
        if (empty($field_input_data['pdf_press_entries']) || empty($field_input_data['pdf_press_template']) || empty($field_input_data['pdf_press_fieldname'])) {
            return $field_input_data;
        }

        // convert entries to utilize hyphens instead of pipes (since pipes are not allowed)
        $entries = str_replace('|', '-', $field_input_data['pdf_press_entries']);

        // generate pdf
        $pdf_template_path = $field_input_data['pdf_press_template'] . '/' . $entries;
        $settings = array(
            'orientation' => 'portrait',
            'size' => 'letter',
            'compress' => '1',
            'encrypt' => false,
            'filename' => 'Solano Custom Report',
            'cache_enabled' => false
        );
        $pdf_output = ee()->extensions->call('pdf_press_generate_pdf', $pdf_template_path, $settings);

        // STORE PDF
        $docroot = $_SERVER['DOCUMENT_ROOT'];
        $upload_path = '/public/uploads/images/'; // TODO get path from upload_dir id from $field_input_data['pdf_press_upload_dir']
        $filename = 'custom-report-' . date('YmdHis') . '-' . rand(10000, 9999999) . '.pdf';
        file_put_contents($docroot . $upload_path . $filename, $pdf_output);

        // for determining field_id
        $field = $field_input_data['pdf_press_fieldname'];

        // if not, use built-in expressionengine email
        $email = $this->send_email($pdf_output);
    }

    function send_email($attachment)
    {
        ee()->load->library('email');
        ee()->load->helper('text');

        ee()->email->wordwrap = true;
        ee()->email->mailtype = 'text';
        ee()->email->from('jmbelite16@gmail.com');
        ee()->email->to('hello@jarrettbarnett.com');
        ee()->email->subject('Report Generated');
        ee()->email->message(entities_to_ascii('Your report has been generated and can be found attached to this email.'));
        ee()->email->Send();
    }
}

/* End of file pi.jb_attachpdf.php */
/* Location: ./system/expressionengine/third_party/jb_pdf_press_email/pi.jb_attachpdf.php */