<?php

/**
 * FacturaScripts Integration Module
 */

class Solwed_FacturaScripts_Integration
{

    public function __construct()
    {
        add_action('elementor_pro/forms/new_record', array($this, 'handle_elementor_form_submission'), 10, 2);
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings()
    {
        add_settings_section(
            'solwed_facturascripts_section',
            'Configuración de la API de FacturaScripts',
            array($this, 'render_api_section'),
            'solwed-wp'
        );

        add_settings_field(
            'solwed_facturascripts_api_key',
            'Clave API',
            array($this, 'render_api_key_field'),
            'solwed-wp',
            'solwed_facturascripts_section'
        );

        add_settings_field(
            'solwed_facturascripts_api_url',
            'URL base de la API',
            array($this, 'render_api_url_field'),
            'solwed-wp',
            'solwed_facturascripts_section'
        );

        register_setting('solwed_settings', 'solwed_facturascripts_api_key');
        register_setting('solwed_settings', 'solwed_facturascripts_api_url');
    }

    public function render_api_section()
    {
        echo '<div class="solwed-settings-section">';
        echo '</div>';
    }

    public function render_api_key_field()
    {
        $api_key = get_option('solwed_facturascripts_api_key', '');
        echo '<div class="solwed-field-group">';
        echo '<input type="password" name="solwed_facturascripts_api_key" 
          value="' . esc_attr($api_key) . '" class="regular-text" 
          placeholder="Enter your API key">';
        echo '<p class="description">Encuéntrelo en el panel de administración de FacturaScripts</p>';
        echo '</div>';
    }

    public function render_api_url_field()
    {
        $api_url = get_option('solwed_facturascripts_api_url', '');
        echo '<div class="solwed-field-group">';
        echo '<input type="url" name="solwed_facturascripts_api_url" 
          value="' . esc_attr($api_url) . '" class="regular-text" 
          placeholder="https://su-dominio-de-facturascripts.com/api/3/">';
        echo '<p class="description">Ejemplo: http://host.docker.internal/api/3/</p>';
        echo '</div>';
    }

    public function handle_elementor_form_submission($record, $ajax_handler)
    {
        $api_key = get_option('solwed_facturascripts_api_key');
        $api_base_url = get_option('solwed_facturascripts_api_url');

        if (empty($api_key) || empty($api_base_url)) {
            error_log('[FS-Integration] API credentials not configured');
            return;
        }

        // Remove trailing slash from base URL if present
        $api_base_url = rtrim($api_base_url, '/');

        $api_url_contactos = $api_base_url . '/contactos';
        $api_url_oportunidades = $api_base_url . '/crmoportunidades';
        $api_url_upload = $api_base_url . '/customUploadFiles';
        $api_url_attachments = $api_base_url . '/attachedfilerelations';

        try {
            // Get form data
            $form_data = $record->get_formatted_data();
            $raw_fields = $record->get('fields');
            error_log('[FS-Integration] Form data: ' . print_r($form_data, true));

            // Prepare contact data
            $contact_data = [
                'nombre' => $form_data['Name'] ?? '',
                'email' => $form_data['Email'] ?? '',
                'observaciones' => 'Contacto desde web. \n ' . ($form_data['Observaciones'] ?? ''),
                'descripcion' => $form_data['Name'] ?? '',
                'fechaalta' => date('d-m-Y')
            ];

            // Validate required fields
            if (empty($contact_data['nombre']) || empty($contact_data['email'])) {
                throw new Exception('Missing required fields');
            }

            // Send contact to FacturaScripts
            $response = wp_remote_post($api_url_contactos, [
                'headers' => ['Token' => $api_key],
                'body' => $contact_data,
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            error_log("[FS-Integration] Contact Response: $code | $body");

            if ($code !== 200) {
                throw new Exception("Contact API Error: HTTP $code");
            }

            // Parse response to get contact ID
            $response_data = json_decode($body, true);
            $idcontacto = $response_data['data']['idcontacto'] ?? null;

            if (!$idcontacto) {
                throw new Exception('No contact ID received from API');
            }

            // Prepare opportunity data
            $opportunity_data = [
                'descripcion' => 'Contacto desde web. \n ' . ($form_data['Observaciones'] ?? ''),
                'fecha' => date('d-m-Y'),
                'hora' => date('H:i'),
                'idcontacto' => $idcontacto,
                'idestado' => 1
            ];

            // Send opportunity to FacturaScripts
            $response2 = wp_remote_post($api_url_oportunidades, [
                'headers' => ['Token' => $api_key],
                'body' => $opportunity_data,
                'timeout' => 30
            ]);

            if (is_wp_error($response2)) {
                throw new Exception($response2->get_error_message());
            }

            $code2 = wp_remote_retrieve_response_code($response2);
            $body2 = wp_remote_retrieve_body($response2);

            error_log("[FS-Integration] Opportunity Response: $code2 | $body2");

            if ($code2 < 200 || $code2 >= 300) {
                throw new Exception("Opportunity API Error: HTTP $code2");
            }

            // Parse opportunity response to get opportunity ID
            $opportunity_response_data = json_decode($body2, true);
            $opportunity_id = $opportunity_response_data['data']['id'] ?? null;

            if (!$opportunity_id) {
                throw new Exception('No opportunity ID received from API');
            }

            error_log("[FS-Integration] Opportunity ID: $opportunity_id");

            // FILE UPLOAD AND LINKING
            $file_field_id = 'field_8d7fb65';
            $uploaded_file_ids = []; // Store uploaded file IDs for linking

            error_log('[FS-Integration] === FILE UPLOAD START ===');

            if (isset($raw_fields[$file_field_id]) && !empty($raw_fields[$file_field_id]['raw_value'])) {
                $file_paths_string = $raw_fields[$file_field_id]['raw_value'];
                error_log("[FS-Integration] File paths string: $file_paths_string");

                // Split the comma-separated file paths and trim whitespace
                $file_paths = array_map('trim', explode(',', $file_paths_string));

                error_log("[FS-Integration] Found " . count($file_paths) . " file(s) to upload");

                foreach ($file_paths as $index => $file_path) {
                    error_log("[FS-Integration] Processing file " . ($index + 1) . ": $file_path");

                    if (file_exists($file_path)) {
                        error_log("[FS-Integration] File exists! Size: " . filesize($file_path) . " bytes");

                        // Detect MIME type
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime_type = finfo_file($finfo, $file_path);
                        finfo_close($finfo);

                        $file_name = basename($file_path);

                        // Upload file
                        $post_fields = [
                            'files[]' => new CURLFile($file_path, $mime_type, $file_name)
                        ];

                        $headers = [
                            'Token: ' . $api_key,
                        ];

                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $api_url_upload);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                        $upload_response = curl_exec($ch);
                        $upload_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                        if (curl_errno($ch)) {
                            error_log("[FS-Integration] cURL error for file $file_name: " . curl_error($ch));
                        } else {
                            error_log("[FS-Integration] Upload response for $file_name: $upload_code | $upload_response");

                            // Parse upload response to get file ID
                            if ($upload_code >= 200 && $upload_code < 300) {
                                $upload_data = json_decode($upload_response, true);
                                // The idfile is inside the files array
                                $idfile = $upload_data['files'][0]['idfile'] ?? null;

                                if ($idfile) {
                                    $uploaded_file_ids[] = $idfile;
                                    error_log("[FS-Integration] File uploaded successfully with ID: $idfile");
                                } else {
                                    error_log("[FS-Integration] No idfile received for $file_name. Response structure: " . print_r($upload_data, true));
                                }
                            }
                        }

                        curl_close($ch);
                    } else {
                        error_log("[FS-Integration] File NOT FOUND at: $file_path");
                    }
                }
            } else {
                error_log('[FS-Integration] No file uploaded or file field not found');
            }

            error_log('[FS-Integration] === FILE UPLOAD END ===');

            // LINK FILES TO OPPORTUNITY
            error_log('[FS-Integration] === FILE LINKING START ===');

            if (!empty($uploaded_file_ids)) {
                error_log("[FS-Integration] Linking " . count($uploaded_file_ids) . " files to opportunity $opportunity_id");

                foreach ($uploaded_file_ids as $index => $idfile) {
                    error_log("[FS-Integration] Linking file ID $idfile to opportunity $opportunity_id");

                    $attachment_data = [
                        'modelid' => $opportunity_id,
                        'modelcode' => $opportunity_id,
                        'model' => 'CrmOportunidad',
                        'idfile' => $idfile
                    ];

                    $response_attachment = wp_remote_post($api_url_attachments, [
                        'headers' => ['Token' => $api_key],
                        'body' => $attachment_data,
                        'timeout' => 30
                    ]);

                    if (is_wp_error($response_attachment)) {
                        error_log("[FS-Integration] Attachment link error for file $idfile: " . $response_attachment->get_error_message());
                        continue;
                    }

                    $attachment_code = wp_remote_retrieve_response_code($response_attachment);
                    $attachment_body = wp_remote_retrieve_body($response_attachment);

                    error_log("[FS-Integration] Attachment link response for file $idfile: $attachment_code | $attachment_body");

                    if ($attachment_code < 200 || $attachment_code >= 300) {
                        error_log("[FS-Integration] Attachment link failed for file $idfile: HTTP $attachment_code");
                    } else {
                        error_log("[FS-Integration] File $idfile successfully linked to opportunity $opportunity_id");
                    }
                }
            } else {
                error_log('[FS-Integration] No files to link to opportunity');
            }

            error_log('[FS-Integration] === FILE LINKING END ===');

            error_log('[FS-Integration] SUCCESS: All operations completed');
        } catch (Exception $e) {
            error_log('[FS-Integration] ERROR: ' . $e->getMessage());
        }
    }
}
