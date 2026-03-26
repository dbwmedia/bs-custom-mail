/**
 * PDF Template Editor - Modern Black/White Design
 */
import { useState, useEffect, useCallback } from '@wordpress/element'
import { __ } from '@wordpress/i18n'
import apiFetch from '@wordpress/api-fetch'
import { PDFTemplate, PDFTemplateConfig } from '../types'

interface Props {
  template?: PDFTemplate
  mode: 'create' | 'edit'
  onCancel: () => void
  onSave: (template: PDFTemplate) => void
  onDelete?: (template: PDFTemplate) => void
}

const defaultConfig: PDFTemplateConfig = {
  wert: { x: 50, y: 50, fontSize: 24 },
  code: { x: 50, y: 100, fontSize: 16 },
  name: { x: 50, y: 150, fontSize: 18 },
  expiry: { x: 50, y: 200, fontSize: 14 },
}

const fields = [
  { key: 'wert' as const, label: 'Gutscheinwert', color: '#000' },
  { key: 'code' as const, label: 'Gutscheincode', color: '#333' },
  { key: 'name' as const, label: 'Empfänger', color: '#666' },
  { key: 'expiry' as const, label: 'Ablaufdatum', color: '#999' },
]

export function PDFTemplateEditor({
  template,
  mode,
  onCancel,
  onSave,
  onDelete,
}: Props) {
  const [templateName, setTemplateName] = useState(
    template?.template_name || '',
  )
  const [templateKey, setTemplateKey] = useState(template?.template_key || '')
  const [attachmentId, setAttachmentId] = useState(template?.attachment_id || 0)
  const [attachmentUrl, setAttachmentUrl] = useState(
    template?.attachment_url || '',
  )
  const [config, setConfig] = useState<PDFTemplateConfig>(defaultConfig)
  const [isDragging, setIsDragging] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (template?.template_config) {
      try {
        const parsed =
          typeof template.template_config === 'string'
            ? JSON.parse(template.template_config)
            : template.template_config
        setConfig({ ...defaultConfig, ...parsed })
      } catch {
        setConfig(defaultConfig)
      }
    }
  }, [template])

  const isImage = attachmentUrl.match(/\.(jpg|jpeg|png|webp)$/i)

  const handleSelectFile = () => {
    // @ts-ignore - WordPress media uploader
    const mediaUploader = wp.media({
      title: __('PDF Template auswählen', 'bs-custom-mail'),
      button: { text: __('Verwenden', 'bs-custom-mail') },
      multiple: false,
      library: {
        type: ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
      },
    })

    mediaUploader.on('select', () => {
      const attachment = mediaUploader.state().get('selection').first().toJSON()
      setAttachmentId(attachment.id)
      setAttachmentUrl(attachment.url)
      setLoading(false)
    })

    mediaUploader.open()
  }

  const handleMouseDown = (field: string) => (e: React.MouseEvent) => {
    e.preventDefault()
    setIsDragging(field)
  }

  const handleMouseMove = useCallback(
    (e: MouseEvent) => {
      if (!isDragging) return

      const canvas = document.getElementById('pdf-canvas')
      if (!canvas) return

      const rect = canvas.getBoundingClientRect()
      const x = ((e.clientX - rect.left) / rect.width) * 210
      const y = ((e.clientY - rect.top) / rect.height) * 297

      setConfig((prev) => ({
        ...prev,
        [isDragging]: { ...prev[isDragging as keyof PDFTemplateConfig], x, y },
      }))
    },
    [isDragging],
  )

  const handleMouseUp = useCallback(() => {
    setIsDragging(null)
  }, [])

  useEffect(() => {
    if (isDragging) {
      document.addEventListener('mousemove', handleMouseMove)
      document.addEventListener('mouseup', handleMouseUp)
    }
    return () => {
      document.removeEventListener('mousemove', handleMouseMove)
      document.removeEventListener('mouseup', handleMouseUp)
    }
  }, [isDragging, handleMouseMove, handleMouseUp])

  const handleSaveClick = async () => {
    setSaving(true)
    try {
      // Generate template_key from name if not set
      const key =
        templateKey ||
        templateName
          .toLowerCase()
          .replace(/\s+/g, '_')
          .replace(/[^a-z0-9_]/g, '')

      const data = {
        template_name: templateName,
        template_key: key,
        attachment_id: parseInt(attachmentId.toString(), 10),
        template_config: JSON.stringify(config),
        font_size: 16,
      }

      console.log('Saving PDF template:', { mode, data })

      if (mode === 'edit' && template?.id) {
        const response = await apiFetch({
          path: `bs-custom-mail/v1/pdf-templates/${template.id}`,
          method: 'POST',
          data,
        })
        console.log('Update response:', response)
        onSave(response as PDFTemplate)
      } else {
        const response = await apiFetch({
          path: 'bs-custom-mail/v1/pdf-templates',
          method: 'POST',
          data,
        })
        console.log('Create response:', response)
        onSave(response as PDFTemplate)
      }
    } catch (error: any) {
      console.error('Error saving PDF template:', error)
      const errorMessage = error?.message || __('Unbekannter Fehler', 'bs-custom-mail')
      alert(__('Fehler beim Speichern: ', 'bs-custom-mail') + errorMessage)
    } finally {
      setSaving(false)
    }
  }

  // Spinner component
  const Spinner = () => (
    <div
      style={{
        width: '16px',
        height: '16px',
        border: '2px solid rgba(255,255,255,0.3)',
        borderTop: '2px solid #fff',
        borderRadius: '50%',
        animation: 'spin 1s linear infinite',
      }}
    />
  )

  return (
    <div
      style={{ display: 'grid', gap: '24px', gridTemplateColumns: '1fr 320px' }}
    >
      <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
        <div
          id='pdf-canvas'
          style={{
            aspectRatio: '210/297',
            background: '#fff',
            border: '1px solid #e5e7eb',
            position: 'relative',
            overflow: 'hidden',
            borderRadius: '12px',
            boxShadow: '0 1px 3px rgba(0,0,0,0.05)',
          }}
        >
          {attachmentUrl ? (
            <>
              {isImage ? (
                <img
                  src={attachmentUrl}
                  alt='Template'
                  style={{
                    width: '100%',
                    height: '100%',
                    objectFit: 'contain',
                  }}
                />
              ) : (
                <div
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    height: '100%',
                    background: '#f9fafb',
                    color: '#6b7280',
                  }}
                >
                  <div style={{ textAlign: 'center' }}>
                    <div
                      style={{
                        marginBottom: '16px',
                        opacity: 0.5,
                        fontSize: '48px',
                      }}
                    >
                      ⊞
                    </div>
                    <p>PDF Template hochgeladen</p>
                  </div>
                </div>
              )}

              {loading && (
                <div
                  style={{
                    position: 'absolute',
                    inset: 0,
                    background: 'rgba(255,255,255,0.9)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                  }}
                >
                  <div
                    style={{
                      width: '32px',
                      height: '32px',
                      border: '2px solid #e5e7eb',
                      borderTop: '2px solid #000',
                      borderRadius: '50%',
                      animation: 'spin 1s linear infinite',
                    }}
                  />
                </div>
              )}

              {fields.map((field) => (
                <div
                  key={field.key}
                  style={{
                    position: 'absolute',
                    left: `${(config[field.key].x / 210) * 100}%`,
                    top: `${(config[field.key].y / 297) * 100}%`,
                    transform: 'translate(-50%, -50%)',
                    padding: '8px 16px',
                    background: isDragging === field.key ? '#000' : '#fff',
                    color: isDragging === field.key ? '#fff' : field.color,
                    border: '1px solid #000',
                    borderRadius: '6px',
                    cursor: 'move',
                    fontSize: `${config[field.key].fontSize}px`,
                    fontWeight: 600,
                    whiteSpace: 'nowrap',
                    boxShadow:
                      isDragging === field.key
                        ? '0 4px 12px rgba(0,0,0,0.2)'
                        : '0 2px 8px rgba(0,0,0,0.1)',
                    zIndex: isDragging === field.key ? 100 : 10,
                  }}
                  onMouseDown={handleMouseDown(field.key)}
                >
                  {field.label}
                </div>
              ))}
            </>
          ) : (
            <div
              style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                height: '100%',
                background: '#f9fafb',
                border: '2px dashed #e5e7eb',
                borderRadius: '8px',
                margin: '24px',
              }}
            >
              <button
                onClick={handleSelectFile}
                style={{
                  padding: '16px 32px',
                  background: '#000',
                  color: '#fff',
                  border: 'none',
                  borderRadius: '8px',
                  cursor: 'pointer',
                  fontSize: '14px',
                  fontWeight: 500,
                }}
              >
                {__('Hintergrund auswählen', 'bs-custom-mail')}
              </button>
            </div>
          )}
        </div>
      </div>

      <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
        <div
          style={{
            background: '#fff',
            borderRadius: '12px',
            border: '1px solid #e5e7eb',
            overflow: 'hidden',
          }}
        >
          <div
            style={{
              padding: '20px',
              borderBottom: '1px solid #e5e7eb',
              background: '#fafafa',
            }}
          >
            <h3
              style={{
                margin: 0,
                fontSize: '14px',
                fontWeight: 700,
                color: '#000',
                textTransform: 'uppercase',
                letterSpacing: '0.5px',
              }}
            >
              {__('Template Einstellungen', 'bs-custom-mail')}
            </h3>
          </div>
          <div
            style={{
              padding: '20px',
              display: 'flex',
              flexDirection: 'column',
              gap: '20px',
            }}
          >
            <div>
              <label
                style={{
                  fontSize: '13px',
                  fontWeight: 600,
                  color: '#374151',
                  marginBottom: '8px',
                  display: 'block',
                }}
              >
                {__('Name', 'bs-custom-mail')} *
              </label>
              <input
                type='text'
                value={templateName}
                onChange={(e) => {
                  setTemplateName(e.target.value)
                  if (!templateKey) {
                    setTemplateKey(
                      e.target.value
                        .toLowerCase()
                        .replace(/\s+/g, '_')
                        .replace(/[^a-z0-9_]/g, ''),
                    )
                  }
                }}
                placeholder={__('z.B. Standard Gutschein', 'bs-custom-mail')}
                style={{
                  width: '100%',
                  padding: '10px 12px',
                  border: '1px solid #e5e7eb',
                  borderRadius: '8px',
                  fontSize: '14px',
                }}
              />
            </div>

            {mode === 'create' && (
              <div>
                <label
                  style={{
                    fontSize: '13px',
                    fontWeight: 600,
                    color: '#374151',
                    marginBottom: '8px',
                    display: 'block',
                  }}
                >
                  {__('Template Key', 'bs-custom-mail')} *
                </label>
                <input
                  type='text'
                  value={templateKey}
                  onChange={(e) =>
                    setTemplateKey(
                      e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''),
                    )
                  }
                  placeholder='standard_gutschein'
                  style={{
                    width: '100%',
                    padding: '10px 12px',
                    border: '1px solid #e5e7eb',
                    borderRadius: '8px',
                    fontSize: '14px',
                    fontFamily: 'monospace',
                  }}
                />
                <p
                  style={{
                    margin: '4px 0 0',
                    fontSize: '12px',
                    color: '#6b7280',
                  }}
                >
                  {__(
                    'Nur Kleinbuchstaben, Zahlen und Unterstriche',
                    'bs-custom-mail',
                  )}
                </p>
              </div>
            )}

            <div>
              <label
                style={{
                  fontSize: '13px',
                  fontWeight: 600,
                  color: '#374151',
                  marginBottom: '8px',
                  display: 'block',
                }}
              >
                {__('Hintergrund', 'bs-custom-mail')} *
              </label>
              {attachmentUrl ? (
                <div
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '12px',
                    padding: '12px',
                    background: '#f9fafb',
                    borderRadius: '8px',
                  }}
                >
                  <div
                    style={{
                      width: '40px',
                      height: '40px',
                      background: '#fff',
                      borderRadius: '6px',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      border: '1px solid #e5e7eb',
                      fontSize: '20px',
                    }}
                  >
                    {isImage ? '🖼️' : '📄'}
                  </div>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <p
                      style={{
                        margin: 0,
                        fontSize: '13px',
                        fontWeight: 500,
                        color: '#000',
                        whiteSpace: 'nowrap',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                      }}
                    >
                      {template?.template_name || 'Template'}
                    </p>
                    <p
                      style={{
                        margin: '2px 0 0',
                        fontSize: '12px',
                        color: '#6b7280',
                      }}
                    >
                      {isImage ? 'Bild' : 'PDF'}
                    </p>
                  </div>
                  <button
                    onClick={() => {
                      setAttachmentId(0)
                      setAttachmentUrl('')
                    }}
                    style={{
                      padding: '6px',
                      background: 'transparent',
                      border: 'none',
                      color: '#ef4444',
                      cursor: 'pointer',
                      borderRadius: '4px',
                    }}
                  >
                    ✕
                  </button>
                </div>
              ) : (
                <button
                  onClick={handleSelectFile}
                  style={{
                    width: '100%',
                    padding: '12px',
                    background: '#f9fafb',
                    border: '1px dashed #d1d5db',
                    borderRadius: '8px',
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '8px',
                    color: '#6b7280',
                  }}
                >
                  🖼️ {__('Datei auswählen', 'bs-custom-mail')}
                </button>
              )}
            </div>
          </div>
        </div>

        <div
          style={{
            background: '#fff',
            borderRadius: '12px',
            border: '1px solid #e5e7eb',
            overflow: 'hidden',
          }}
        >
          <div
            style={{
              padding: '20px',
              borderBottom: '1px solid #e5e7eb',
              background: '#fafafa',
            }}
          >
            <h3
              style={{
                margin: 0,
                fontSize: '14px',
                fontWeight: 700,
                color: '#000',
                textTransform: 'uppercase',
                letterSpacing: '0.5px',
              }}
            >
              {__('Textfelder Position', 'bs-custom-mail')}
            </h3>
          </div>
          <div style={{ padding: '20px' }}>
            <div
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '8px',
                padding: '12px',
                background: '#f9fafb',
                borderRadius: '8px',
                marginBottom: '16px',
              }}
            >
              <span>🖱️</span>
              <span style={{ fontSize: '13px', color: '#374151' }}>
                {__('Felder per Drag & Drop positionieren', 'bs-custom-mail')}
              </span>
            </div>

            <div
              style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}
            >
              {fields.map((field) => (
                <div
                  key={field.key}
                  style={{
                    padding: '12px',
                    border: '1px solid #e5e7eb',
                    borderRadius: '8px',
                  }}
                >
                  <div
                    style={{
                      display: 'flex',
                      alignItems: 'center',
                      gap: '8px',
                      marginBottom: '8px',
                    }}
                  >
                    <div
                      style={{
                        width: '8px',
                        height: '8px',
                        background: field.color,
                        borderRadius: '50%',
                      }}
                    />
                    <span style={{ fontSize: '13px', fontWeight: 600 }}>
                      {field.label}
                    </span>
                  </div>
                  <div
                    style={{
                      display: 'grid',
                      gridTemplateColumns: '1fr 1fr',
                      gap: '8px',
                    }}
                  >
                    <input
                      type='number'
                      value={Math.round(config[field.key].x)}
                      onChange={(e) =>
                        setConfig((prev) => ({
                          ...prev,
                          [field.key]: {
                            ...prev[field.key],
                            x: parseInt(e.target.value) || 0,
                          },
                        }))
                      }
                      placeholder='X'
                      style={{
                        padding: '8px',
                        border: '1px solid #e5e7eb',
                        borderRadius: '6px',
                        fontSize: '13px',
                      }}
                    />
                    <input
                      type='number'
                      value={Math.round(config[field.key].y)}
                      onChange={(e) =>
                        setConfig((prev) => ({
                          ...prev,
                          [field.key]: {
                            ...prev[field.key],
                            y: parseInt(e.target.value) || 0,
                          },
                        }))
                      }
                      placeholder='Y'
                      style={{
                        padding: '8px',
                        border: '1px solid #e5e7eb',
                        borderRadius: '6px',
                        fontSize: '13px',
                      }}
                    />
                  </div>
                  <div style={{ marginTop: '8px' }}>
                    <input
                      type='number'
                      value={config[field.key].fontSize}
                      onChange={(e) =>
                        setConfig((prev) => ({
                          ...prev,
                          [field.key]: {
                            ...prev[field.key],
                            fontSize: parseInt(e.target.value) || 12,
                          },
                        }))
                      }
                      placeholder='Schriftgröße'
                      style={{
                        width: '100%',
                        padding: '8px',
                        border: '1px solid #e5e7eb',
                        borderRadius: '6px',
                        fontSize: '13px',
                      }}
                    />
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        <div style={{ display: 'flex', gap: '12px', flexDirection: 'column' }}>
          <button
            onClick={onCancel}
            disabled={saving}
            style={{
              padding: '14px',
              background: '#f3f4f6',
              color: '#374151',
              border: 'none',
              borderRadius: '10px',
              cursor: 'pointer',
              fontWeight: 600,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              gap: '8px',
            }}
          >
            ← {__('Zurück', 'bs-custom-mail')}
          </button>
          <button
            onClick={handleSaveClick}
            disabled={saving || !templateName || !attachmentId || !templateKey}
            style={{
              padding: '14px',
              background:
                saving || !templateName || !attachmentId || !templateKey
                  ? '#9ca3af'
                  : '#000',
              color: '#fff',
              border: 'none',
              borderRadius: '10px',
              cursor:
                saving || !templateName || !attachmentId || !templateKey
                  ? 'not-allowed'
                  : 'pointer',
              fontWeight: 600,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              gap: '8px',
            }}
          >
            {saving ? (
              <>
                <Spinner />
                {__('Speichern...', 'bs-custom-mail')}
              </>
            ) : (
              <>💾 {__('Speichern', 'bs-custom-mail')}</>
            )}
          </button>
          {mode === 'edit' && onDelete && (
            <button
              onClick={() => {
                if (
                  template &&
                  window.confirm(
                    __('Template wirklich löschen?', 'bs-custom-mail'),
                  )
                ) {
                  onDelete(template)
                }
              }}
              disabled={saving}
              style={{
                padding: '14px',
                background: '#fef2f2',
                color: '#dc2626',
                border: '1px solid #fecaca',
                borderRadius: '10px',
                cursor: 'pointer',
                fontWeight: 600,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                gap: '8px',
              }}
            >
              🗑️ {__('Löschen', 'bs-custom-mail')}
            </button>
          )}
        </div>
      </div>
      <style>{`
				@keyframes spin {
					from { transform: rotate(0deg); }
					to { transform: rotate(360deg); }
				}
			`}</style>
    </div>
  )
}
