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
        return $this->return_data = ee()->TMPL->parse_variables($tagdata, $variables);
    }
}

/* End of file pi.jb_attachpdf.php */
/* Location: ./system/expressionengine/third_party/jb_pdf_press_email/pi.jb_attachpdf.php */