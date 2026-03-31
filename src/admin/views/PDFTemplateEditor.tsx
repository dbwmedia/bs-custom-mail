/**
 * Enhanced PDF Template Editor
 * Features: Drag & Drop fields, Colorpicker background, Paper format selection
 */
import { useState, useEffect, useCallback } from '@wordpress/element'
import { __ } from '@wordpress/i18n'
import apiFetch from '@wordpress/api-fetch'
import { PDFTemplate, PDFTemplateConfig, PDFFieldDefinition } from '../types'

interface Props {
  template?: PDFTemplate
  mode: 'create' | 'edit'
  onCancel: () => void
  onSave: (template: PDFTemplate) => void
  onDelete?: (template: PDFTemplate) => void
}

// Paper format dimensions in mm
const PAPER_FORMATS = {
  A4: { width: 210, height: 297 },
  A5: { width: 148, height: 210 },
  A6: { width: 105, height: 148 },
}

// Available fields configuration
const AVAILABLE_FIELDS: PDFFieldDefinition[] = [
  { key: 'wert', label: 'Gutscheinwert', color: '#059669', defaultPosition: { x: 105, y: 100, fontSize: 28 } },
  { key: 'code', label: 'Gutscheincode', color: '#1e40af', defaultPosition: { x: 105, y: 140, fontSize: 18 } },
  { key: 'name', label: 'Empfänger', color: '#374151', defaultPosition: { x: 105, y: 180, fontSize: 16 } },
  { key: 'expiry', label: 'Ablaufdatum', color: '#6b7280', defaultPosition: { x: 105, y: 220, fontSize: 14 } },
  { key: 'adressant', label: 'Adressant', color: '#7c3aed', defaultPosition: { x: 20, y: 40, fontSize: 12 }, optional: true },
  { key: 'notiz', label: 'Notiz', color: '#dc2626', defaultPosition: { x: 105, y: 260, fontSize: 12 }, optional: true },
]

const defaultConfig: PDFTemplateConfig = {
  wert: { x: 105, y: 100, fontSize: 28 },
  code: { x: 105, y: 140, fontSize: 18 },
  name: { x: 105, y: 180, fontSize: 16 },
  expiry: { x: 105, y: 220, fontSize: 14 },
  adressant: { x: 20, y: 40, fontSize: 12 },
  notiz: { x: 105, y: 260, fontSize: 12 },
}

const defaultActiveFields = ['wert', 'code', 'name', 'expiry']

export function PDFTemplateEditor({
  template,
  mode,
  onCancel,
  onSave,
  onDelete,
}: Props) {
  const [templateName, setTemplateName] = useState(template?.template_name || '')
  const [templateKey, setTemplateKey] = useState(template?.template_key || '')
  const [attachmentId, setAttachmentId] = useState(template?.attachment_id || 0)
  const [attachmentUrl, setAttachmentUrl] = useState(template?.attachment_url || '')
  const [config, setConfig] = useState<PDFTemplateConfig>(defaultConfig)
  const [activeFields, setActiveFields] = useState<string[]>(defaultActiveFields)
  const [paperSize, setPaperSize] = useState<'A4' | 'A5' | 'A6'>(template?.paper_size || 'A4')
  const [orientation, setOrientation] = useState<'portrait' | 'landscape'>(template?.orientation || 'portrait')
  const [backgroundType, setBackgroundType] = useState<'color' | 'image'>(template?.background_type || 'color')
  const [backgroundColor, setBackgroundColor] = useState(template?.background_color || '#3b82f6')
  const [isDragging, setIsDragging] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [activeTab, setActiveTab] = useState<'fields' | 'settings'>('fields')

  // Load template data
  useEffect(() => {
    if (template?.template_config) {
      try {
        const parsed = typeof template.template_config === 'string'
          ? JSON.parse(template.template_config)
          : template.template_config
        setConfig({ ...defaultConfig, ...parsed })
      } catch {
        setConfig(defaultConfig)
      }
    }

    if (template?.active_fields) {
      try {
        const parsed = typeof template.active_fields === 'string'
          ? JSON.parse(template.active_fields)
          : template.active_fields
        setActiveFields(parsed.length > 0 ? parsed : defaultActiveFields)
      } catch {
        setActiveFields(defaultActiveFields)
      }
    }

    if (template?.paper_size) setPaperSize(template.paper_size)
    if (template?.orientation) setOrientation(template.orientation)
    if (template?.background_type) setBackgroundType(template.background_type)
    if (template?.background_color) setBackgroundColor(template.background_color)
    if (template?.attachment_id) setAttachmentId(template.attachment_id)
    if (template?.attachment_url) setAttachmentUrl(template.attachment_url)
  }, [template])

  // Get canvas dimensions based on paper size and orientation
  const getCanvasDimensions = () => {
    const format = PAPER_FORMATS[paperSize]
    if (orientation === 'landscape') {
      return { width: format.height, height: format.width }
    }
    return format
  }

  const canvasDims = getCanvasDimensions()
  const isImage = attachmentUrl.match(/\.(jpg|jpeg|png|webp)$/i)

  // Handle media selection
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
      setBackgroundType('image')
    })

    mediaUploader.open()
  }

  // Toggle field visibility
  const toggleField = (fieldKey: string) => {
    setActiveFields(prev => {
      if (prev.includes(fieldKey)) {
        return prev.filter(f => f !== fieldKey)
      }
      return [...prev, fieldKey]
    })
  }

  // Drag handlers
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
      const x = ((e.clientX - rect.left) / rect.width) * canvasDims.width
      const y = ((e.clientY - rect.top) / rect.height) * canvasDims.height

      setConfig((prev) => ({
        ...prev,
        [isDragging]: { ...prev[isDragging as keyof PDFTemplateConfig], x, y },
      }))
    },
    [isDragging, canvasDims]
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

  // Update position from input
  const updatePosition = (fieldKey: string, axis: 'x' | 'y', value: number) => {
    setConfig(prev => ({
      ...prev,
      [fieldKey]: { ...prev[fieldKey as keyof PDFTemplateConfig], [axis]: value },
    }))
  }

  // Update font size
  const updateFontSize = (fieldKey: string, value: number) => {
    setConfig(prev => ({
      ...prev,
      [fieldKey]: { ...prev[fieldKey as keyof PDFTemplateConfig], fontSize: value },
    }))
  }

  // Safe JSON stringify
  const safeStringify = (obj: any): string => {
    return JSON.stringify(obj, (_, value) =>
      typeof value === 'bigint' ? Number(value) : value
    )
  }

  // Save template
  const handleSaveClick = async () => {
    setSaving(true)
    try {
      const key = templateKey || templateName
        .toLowerCase()
        .replace(/\s+/g, '_')
        .replace(/[^a-z0-9_]/g, '')

      // Filter config to only include active fields
      const activeConfig: Partial<PDFTemplateConfig> = {}
      activeFields.forEach(fieldKey => {
        if (config[fieldKey as keyof PDFTemplateConfig]) {
          (activeConfig as any)[fieldKey] = config[fieldKey as keyof PDFTemplateConfig]
        }
      })

      const data = {
        template_name: templateName,
        template_key: key,
        attachment_id: backgroundType === 'image' ? parseInt(attachmentId.toString(), 10) : 0,
        template_config: safeStringify(activeConfig),
        font_size: 16,
        paper_size: paperSize,
        orientation: orientation,
        background_color: backgroundColor,
        background_type: backgroundType,
        active_fields: safeStringify(activeFields),
      }

      if (mode === 'edit' && template?.id) {
        const response = await apiFetch({
          path: `bs-custom-mail/v1/pdf-templates/${template.id}`,
          method: 'POST',
          data,
        })
        onSave(response as PDFTemplate)
      } else {
        const response = await apiFetch({
          path: 'bs-custom-mail/v1/pdf-templates',
          method: 'POST',
          data,
        })
        onSave(response as PDFTemplate)
      }
    } catch (error: any) {
      console.error('Error saving PDF template:', error)
      let errorMessage = __('Unbekannter Fehler', 'bs-custom-mail')
      if (error?.code && error?.message) {
        errorMessage = `[${error.code}] ${error.message}`
      } else if (error?.message) {
        errorMessage = error.message
      }
      alert(__('Fehler beim Speichern: ', 'bs-custom-mail') + errorMessage)
    } finally {
      setSaving(false)
    }
  }

  // Spinner component
  const Spinner = () => (
    <div style={{
      width: '16px',
      height: '16px',
      border: '2px solid rgba(255,255,255,0.3)',
      borderTop: '2px solid #fff',
      borderRadius: '50%',
      animation: 'spin 1s linear infinite',
    }} />
  )

  // Get field definition
  const getFieldDef = (key: string) => AVAILABLE_FIELDS.find(f => f.key === key)

  return (
    <div style={{ display: 'grid', gap: '24px', gridTemplateColumns: '1fr 360px' }}>
      {/* Canvas Area */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
        <div
          id="pdf-canvas"
          style={{
            aspectRatio: `${canvasDims.width}/${canvasDims.height}`,
            background: backgroundType === 'color' ? backgroundColor : '#fff',
            border: '1px solid #e5e7eb',
            position: 'relative',
            overflow: 'hidden',
            borderRadius: '12px',
            boxShadow: '0 4px 6px -1px rgba(0,0,0,0.1)',
          }}
        >
          {/* Background Image */}
          {backgroundType === 'image' && attachmentUrl && isImage && (
            <img
              src={attachmentUrl}
              alt="Template"
              style={{
                width: '100%',
                height: '100%',
                objectFit: 'contain',
                position: 'absolute',
                top: 0,
                left: 0,
              }}
            />
          )}

          {/* PDF Placeholder */}
          {backgroundType === 'image' && attachmentUrl && !isImage && (
            <div style={{
              position: 'absolute',
              inset: 0,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              background: '#f9fafb',
            }}>
              <div style={{ textAlign: 'center', color: '#6b7280' }}>
                <div style={{ fontSize: '48px', marginBottom: '16px', opacity: 0.5 }}>📄</div>
                <p>PDF Template hochgeladen</p>
              </div>
            </div>
          )}

          {/* Draggable Fields */}
          {activeFields.map((fieldKey) => {
            const fieldDef = getFieldDef(fieldKey)
            const fieldConfig = config[fieldKey as keyof PDFTemplateConfig]
            if (!fieldDef || !fieldConfig) return null

            return (
              <div
                key={fieldKey}
                style={{
                  position: 'absolute',
                  left: `${(fieldConfig.x / canvasDims.width) * 100}%`,
                  top: `${(fieldConfig.y / canvasDims.height) * 100}%`,
                  transform: 'translate(-50%, -50%)',
                  padding: '8px 16px',
                  background: isDragging === fieldKey ? fieldDef.color : '#fff',
                  color: isDragging === fieldKey ? '#fff' : fieldDef.color,
                  border: `2px solid ${fieldDef.color}`,
                  borderRadius: '8px',
                  cursor: 'move',
                  fontSize: `${fieldConfig.fontSize || fieldDef.defaultPosition.fontSize}px`,
                  fontWeight: 600,
                  whiteSpace: 'nowrap',
                  boxShadow: isDragging === fieldKey
                    ? '0 8px 25px rgba(0,0,0,0.25)'
                    : '0 2px 8px rgba(0,0,0,0.1)',
                  zIndex: isDragging === fieldKey ? 100 : 10,
                  transition: isDragging === fieldKey ? 'none' : 'box-shadow 0.2s',
                }}
                onMouseDown={handleMouseDown(fieldKey)}
              >
                {fieldDef.label}
              </div>
            )
          })}

          {/* Empty State */}
          {activeFields.length === 0 && (
            <div style={{
              position: 'absolute',
              inset: 0,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              flexDirection: 'column',
              color: 'rgba(255,255,255,0.5)',
            }}>
              <div style={{ fontSize: '48px', marginBottom: '16px' }}>📋</div>
              <p>Keine Felder ausgewählt</p>
            </div>
          )}
        </div>

        {/* Quick Info */}
        <div style={{
          display: 'flex',
          gap: '16px',
          padding: '16px',
          background: '#f9fafb',
          borderRadius: '8px',
          fontSize: '13px',
          color: '#6b7280',
        }}>
          <span>📐 {paperSize} {orientation === 'portrait' ? 'Hoch' : 'Quer'}</span>
          <span>•</span>
          <span>{activeFields.length} Felder aktiv</span>
          {backgroundType === 'color' && (
            <>
              <span>•</span>
              <span style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                🎨 Farbe:
                <span style={{
                  width: '16px',
                  height: '16px',
                  borderRadius: '4px',
                  background: backgroundColor,
                  border: '1px solid #d1d5db',
                }} />
              </span>
            </>
          )}
        </div>
      </div>

      {/* Sidebar */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
        {/* Tabs */}
        <div style={{
          display: 'flex',
          gap: '4px',
          background: '#f3f4f6',
          padding: '4px',
          borderRadius: '10px',
        }}>
          <button
            onClick={() => setActiveTab('fields')}
            style={{
              flex: 1,
              padding: '10px 16px',
              borderRadius: '8px',
              border: 'none',
              background: activeTab === 'fields' ? '#fff' : 'transparent',
              color: activeTab === 'fields' ? '#000' : '#6b7280',
              fontWeight: 600,
              fontSize: '14px',
              cursor: 'pointer',
              boxShadow: activeTab === 'fields' ? '0 1px 3px rgba(0,0,0,0.1)' : 'none',
            }}
          >
            Felder
          </button>
          <button
            onClick={() => setActiveTab('settings')}
            style={{
              flex: 1,
              padding: '10px 16px',
              borderRadius: '8px',
              border: 'none',
              background: activeTab === 'settings' ? '#fff' : 'transparent',
              color: activeTab === 'settings' ? '#000' : '#6b7280',
              fontWeight: 600,
              fontSize: '14px',
              cursor: 'pointer',
              boxShadow: activeTab === 'settings' ? '0 1px 3px rgba(0,0,0,0.1)' : 'none',
            }}
          >
            Einstellungen
          </button>
        </div>

        {/* Fields Tab */}
        {activeTab === 'fields' && (
          <>
            {/* Template Name */}
            <div style={{
              background: '#fff',
              borderRadius: '12px',
              border: '1px solid #e5e7eb',
              padding: '20px',
            }}>
              <label style={{
                fontSize: '13px',
                fontWeight: 600,
                color: '#374151',
                marginBottom: '8px',
                display: 'block',
              }}>
                {__('Template Name', 'bs-custom-mail')} *
              </label>
              <input
                type="text"
                value={templateName}
                onChange={(e) => {
                  setTemplateName(e.target.value)
                  if (!templateKey) {
                    setTemplateKey(
                      e.target.value
                        .toLowerCase()
                        .replace(/\s+/g, '_')
                        .replace(/[^a-z0-9_]/g, '')
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

              {mode === 'create' && (
                <>
                  <label style={{
                    fontSize: '13px',
                    fontWeight: 600,
                    color: '#374151',
                    marginTop: '16px',
                    marginBottom: '8px',
                    display: 'block',
                  }}>
                    {__('Template Key', 'bs-custom-mail')} *
                  </label>
                  <input
                    type="text"
                    value={templateKey}
                    onChange={(e) =>
                      setTemplateKey(
                        e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '')
                      )
                    }
                    placeholder="standard_gutschein"
                    style={{
                      width: '100%',
                      padding: '10px 12px',
                      border: '1px solid #e5e7eb',
                      borderRadius: '8px',
                      fontSize: '14px',
                      fontFamily: 'monospace',
                    }}
                  />
                </>
              )}
            </div>

            {/* Field Selection */}
            <div style={{
              background: '#fff',
              borderRadius: '12px',
              border: '1px solid #e5e7eb',
              overflow: 'hidden',
            }}>
              <div style={{
                padding: '16px 20px',
                borderBottom: '1px solid #e5e7eb',
                background: '#fafafa',
              }}>
                <h3 style={{
                  margin: 0,
                  fontSize: '14px',
                  fontWeight: 700,
                  color: '#000',
                }}>
                  {__('Verfügbare Felder', 'bs-custom-mail')}
                </h3>
                <p style={{
                  margin: '4px 0 0',
                  fontSize: '12px',
                  color: '#6b7280',
                }}>
                  {__('Wählen Sie die Felder aus, die angezeigt werden sollen', 'bs-custom-mail')}
                </p>
              </div>

              <div style={{ padding: '12px' }}>
                {AVAILABLE_FIELDS.map((field) => (
                  <div
                    key={field.key}
                    style={{
                      display: 'flex',
                      alignItems: 'center',
                      gap: '12px',
                      padding: '12px',
                      borderRadius: '8px',
                      background: activeFields.includes(field.key) ? '#f0fdf4' : '#f9fafb',
                      border: activeFields.includes(field.key)
                        ? `1px solid ${field.color}`
                        : '1px solid #e5e7eb',
                      marginBottom: '8px',
                      cursor: 'pointer',
                      transition: 'all 0.2s',
                    }}
                    onClick={() => toggleField(field.key)}
                  >
                    <input
                      type="checkbox"
                      checked={activeFields.includes(field.key)}
                      onChange={() => {}}
                      style={{
                        width: '18px',
                        height: '18px',
                        accentColor: field.color,
                        cursor: 'pointer',
                      }}
                    />
                    <div style={{
                      width: '10px',
                      height: '10px',
                      borderRadius: '50%',
                      background: field.color,
                    }} />
                    <span style={{
                      flex: 1,
                      fontSize: '14px',
                      fontWeight: 500,
                      color: activeFields.includes(field.key) ? '#000' : '#6b7280',
                    }}>
                      {field.label}
                    </span>
                    {field.optional && (
                      <span style={{
                        fontSize: '11px',
                        color: '#9ca3af',
                        background: '#f3f4f6',
                        padding: '2px 6px',
                        borderRadius: '4px',
                      }}>
                        Optional
                      </span>
                    )}
                  </div>
                ))}
              </div>
            </div>

            {/* Position Settings for Active Fields */}
            {activeFields.length > 0 && (
              <div style={{
                background: '#fff',
                borderRadius: '12px',
                border: '1px solid #e5e7eb',
                overflow: 'hidden',
              }}>
                <div style={{
                  padding: '16px 20px',
                  borderBottom: '1px solid #e5e7eb',
                  background: '#fafafa',
                }}>
                  <h3 style={{
                    margin: 0,
                    fontSize: '14px',
                    fontWeight: 700,
                    color: '#000',
                  }}>
                    {__('Position & Größe', 'bs-custom-mail')}
                  </h3>
                </div>

                <div style={{ padding: '16px' }}>
                  <div style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '8px',
                    padding: '10px',
                    background: '#eff6ff',
                    borderRadius: '8px',
                    marginBottom: '16px',
                    fontSize: '13px',
                    color: '#1e40af',
                  }}>
                    <span>💡</span>
                    <span>Felder können per Drag & Drop positioniert werden</span>
                  </div>

                  <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                    {activeFields.map((fieldKey) => {
                      const fieldDef = getFieldDef(fieldKey)
                      const fieldConfig = config[fieldKey as keyof PDFTemplateConfig]
                      if (!fieldDef || !fieldConfig) return null

                      return (
                        <div
                          key={fieldKey}
                          style={{
                            padding: '12px',
                            border: '1px solid #e5e7eb',
                            borderRadius: '8px',
                            background: '#fafafa',
                          }}
                        >
                          <div style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: '8px',
                            marginBottom: '10px',
                          }}>
                            <div style={{
                              width: '8px',
                              height: '8px',
                              borderRadius: '50%',
                              background: fieldDef.color,
                            }} />
                            <span style={{
                              fontSize: '13px',
                              fontWeight: 600,
                              color: '#000',
                            }}>
                              {fieldDef.label}
                            </span>
                          </div>

                          <div style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: '8px',
                          }}>
                            <div>
                              <label style={{
                                fontSize: '11px',
                                color: '#6b7280',
                                marginBottom: '4px',
                                display: 'block',
                              }}>
                                X (mm)
                              </label>
                              <input
                                type="number"
                                value={Math.round(fieldConfig.x)}
                                onChange={(e) => updatePosition(fieldKey, 'x', parseInt(e.target.value) || 0)}
                                style={{
                                  width: '100%',
                                  padding: '6px',
                                  border: '1px solid #e5e7eb',
                                  borderRadius: '6px',
                                  fontSize: '13px',
                                }}
                              />
                            </div>
                            <div>
                              <label style={{
                                fontSize: '11px',
                                color: '#6b7280',
                                marginBottom: '4px',
                                display: 'block',
                              }}>
                                Y (mm)
                              </label>
                              <input
                                type="number"
                                value={Math.round(fieldConfig.y)}
                                onChange={(e) => updatePosition(fieldKey, 'y', parseInt(e.target.value) || 0)}
                                style={{
                                  width: '100%',
                                  padding: '6px',
                                  border: '1px solid #e5e7eb',
                                  borderRadius: '6px',
                                  fontSize: '13px',
                                }}
                              />
                            </div>
                          </div>

                          <div style={{ marginTop: '8px' }}>
                            <label style={{
                              fontSize: '11px',
                              color: '#6b7280',
                              marginBottom: '4px',
                              display: 'block',
                            }}>
                              Schriftgröße (px)
                            </label>
                            <input
                              type="number"
                              value={fieldConfig.fontSize || fieldDef.defaultPosition.fontSize}
                              onChange={(e) => updateFontSize(fieldKey, parseInt(e.target.value) || 12)}
                              style={{
                                width: '100%',
                                padding: '6px',
                                border: '1px solid #e5e7eb',
                                borderRadius: '6px',
                                fontSize: '13px',
                              }}
                            />
                          </div>
                        </div>
                      )
                    })}
                  </div>
                </div>
              </div>
            )}
          </>
        )}

        {/* Settings Tab */}
        {activeTab === 'settings' && (
          <>
            {/* Paper Format */}
            <div style={{
              background: '#fff',
              borderRadius: '12px',
              border: '1px solid #e5e7eb',
              padding: '20px',
            }}>
              <h3 style={{
                margin: '0 0 16px',
                fontSize: '14px',
                fontWeight: 700,
                color: '#000',
              }}>
                {__('Papierformat', 'bs-custom-mail')}
              </h3>

              <div style={{ marginBottom: '16px' }}>
                <label style={{
                  fontSize: '13px',
                  fontWeight: 600,
                  color: '#374151',
                  marginBottom: '8px',
                  display: 'block',
                }}>
                  {__('Format', 'bs-custom-mail')}
                </label>
                <div style={{
                  display: 'grid',
                  gridTemplateColumns: 'repeat(3, 1fr)',
                  gap: '8px',
                }}>
                  {(['A4', 'A5', 'A6'] as const).map((size) => (
                    <button
                      key={size}
                      onClick={() => setPaperSize(size)}
                      style={{
                        padding: '12px',
                        border: paperSize === size ? '2px solid #000' : '1px solid #e5e7eb',
                        borderRadius: '8px',
                        background: paperSize === size ? '#f3f4f6' : '#fff',
                        fontWeight: paperSize === size ? 600 : 400,
                        cursor: 'pointer',
                      }}
                    >
                      {size}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label style={{
                  fontSize: '13px',
                  fontWeight: 600,
                  color: '#374151',
                  marginBottom: '8px',
                  display: 'block',
                }}>
                  {__('Ausrichtung', 'bs-custom-mail')}
                </label>
                <div style={{
                  display: 'grid',
                  gridTemplateColumns: 'repeat(2, 1fr)',
                  gap: '8px',
                }}>
                  <button
                    onClick={() => setOrientation('portrait')}
                    style={{
                      padding: '12px',
                      border: orientation === 'portrait' ? '2px solid #000' : '1px solid #e5e7eb',
                      borderRadius: '8px',
                      background: orientation === 'portrait' ? '#f3f4f6' : '#fff',
                      fontWeight: orientation === 'portrait' ? 600 : 400,
                      cursor: 'pointer',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      gap: '6px',
                    }}
                  >
                    <span>📄</span> Hoch
                  </button>
                  <button
                    onClick={() => setOrientation('landscape')}
                    style={{
                      padding: '12px',
                      border: orientation === 'landscape' ? '2px solid #000' : '1px solid #e5e7eb',
                      borderRadius: '8px',
                      background: orientation === 'landscape' ? '#f3f4f6' : '#fff',
                      fontWeight: orientation === 'landscape' ? 600 : 400,
                      cursor: 'pointer',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      gap: '6px',
                    }}
                  >
                    <span>📄</span> Quer
                  </button>
                </div>
              </div>
            </div>

            {/* Background */}
            <div style={{
              background: '#fff',
              borderRadius: '12px',
              border: '1px solid #e5e7eb',
              overflow: 'hidden',
            }}>
              <div style={{
                padding: '16px 20px',
                borderBottom: '1px solid #e5e7eb',
                background: '#fafafa',
              }}>
                <h3 style={{
                  margin: 0,
                  fontSize: '14px',
                  fontWeight: 700,
                  color: '#000',
                }}>
                  {__('Hintergrund', 'bs-custom-mail')}
                </h3>
              </div>

              <div style={{ padding: '20px' }}>
                {/* Background Type Selection */}
                <div style={{
                  display: 'grid',
                  gridTemplateColumns: 'repeat(2, 1fr)',
                  gap: '8px',
                  marginBottom: '16px',
                }}>
                  <button
                    onClick={() => setBackgroundType('color')}
                    style={{
                      padding: '12px',
                      border: backgroundType === 'color' ? '2px solid #3b82f6' : '1px solid #e5e7eb',
                      borderRadius: '8px',
                      background: backgroundType === 'color' ? '#eff6ff' : '#fff',
                      fontWeight: backgroundType === 'color' ? 600 : 400,
                      cursor: 'pointer',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      gap: '6px',
                      color: backgroundType === 'color' ? '#1e40af' : '#374151',
                    }}
                  >
                    🎨 Farbe
                  </button>
                  <button
                    onClick={() => setBackgroundType('image')}
                    style={{
                      padding: '12px',
                      border: backgroundType === 'image' ? '2px solid #3b82f6' : '1px solid #e5e7eb',
                      borderRadius: '8px',
                      background: backgroundType === 'image' ? '#eff6ff' : '#fff',
                      fontWeight: backgroundType === 'image' ? 600 : 400,
                      cursor: 'pointer',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      gap: '6px',
                      color: backgroundType === 'image' ? '#1e40af' : '#374151',
                    }}
                  >
                    🖼️ Bild/PDF
                  </button>
                </div>

                {/* Color Picker */}
                {backgroundType === 'color' && (
                  <div>
                    <label style={{
                      fontSize: '13px',
                      fontWeight: 600,
                      color: '#374151',
                      marginBottom: '8px',
                      display: 'block',
                    }}>
                      {__('Hintergrundfarbe wählen', 'bs-custom-mail')}
                    </label>
                    <div style={{
                      display: 'flex',
                      gap: '8px',
                      flexWrap: 'wrap',
                      marginBottom: '12px',
                    }}>
                      {['#3b82f6', '#059669', '#dc2626', '#7c3aed', '#ea580c', '#0ea5e9', '#000000', '#ffffff', '#f3f4f6', '#fcd34d'].map((color) => (
                        <button
                          key={color}
                          onClick={() => setBackgroundColor(color)}
                          style={{
                            width: '36px',
                            height: '36px',
                            borderRadius: '8px',
                            background: color,
                            border: backgroundColor === color ? '3px solid #000' : '1px solid #e5e7eb',
                            cursor: 'pointer',
                            boxShadow: color === '#ffffff' ? 'inset 0 0 0 1px #e5e7eb' : 'none',
                          }}
                        />
                      ))}
                    </div>
                    <div style={{
                      display: 'flex',
                      alignItems: 'center',
                      gap: '8px',
                    }}>
                      <input
                        type="color"
                        value={backgroundColor}
                        onChange={(e) => setBackgroundColor(e.target.value)}
                        style={{
                          width: '48px',
                          height: '40px',
                          border: '1px solid #e5e7eb',
                          borderRadius: '8px',
                          cursor: 'pointer',
                        }}
                      />
                      <input
                        type="text"
                        value={backgroundColor}
                        onChange={(e) => setBackgroundColor(e.target.value)}
                        style={{
                          flex: 1,
                          padding: '10px 12px',
                          border: '1px solid #e5e7eb',
                          borderRadius: '8px',
                          fontSize: '14px',
                          fontFamily: 'monospace',
                          textTransform: 'uppercase',
                        }}
                      />
                    </div>
                  </div>
                )}

                {/* Image Upload */}
                {backgroundType === 'image' && (
                  <div>
                    {attachmentUrl ? (
                      <div style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: '12px',
                        padding: '12px',
                        background: '#f9fafb',
                        borderRadius: '8px',
                      }}>
                        <div style={{
                          width: '48px',
                          height: '48px',
                          background: '#fff',
                          borderRadius: '8px',
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          border: '1px solid #e5e7eb',
                          fontSize: '24px',
                        }}>
                          {isImage ? '🖼️' : '📄'}
                        </div>
                        <div style={{ flex: 1, minWidth: 0 }}>
                          <p style={{
                            margin: 0,
                            fontSize: '13px',
                            fontWeight: 500,
                            color: '#000',
                            whiteSpace: 'nowrap',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                          }}>
                            Hintergrund hochgeladen
                          </p>
                          <p style={{
                            margin: '2px 0 0',
                            fontSize: '12px',
                            color: '#6b7280',
                          }}>
                            {isImage ? 'Bild' : 'PDF'}
                          </p>
                        </div>
                        <button
                          onClick={() => {
                            setAttachmentId(0)
                            setAttachmentUrl('')
                          }}
                          style={{
                            padding: '8px',
                            background: '#fef2f2',
                            border: 'none',
                            color: '#dc2626',
                            cursor: 'pointer',
                            borderRadius: '6px',
                          }}
                        >
                          🗑️
                        </button>
                      </div>
                    ) : (
                      <button
                        onClick={handleSelectFile}
                        style={{
                          width: '100%',
                          padding: '16px',
                          background: '#f9fafb',
                          border: '2px dashed #d1d5db',
                          borderRadius: '8px',
                          cursor: 'pointer',
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          gap: '8px',
                          color: '#6b7280',
                          fontSize: '14px',
                        }}
                      >
                        🖼️ {__('Bild oder PDF hochladen', 'bs-custom-mail')}
                      </button>
                    )}
                  </div>
                )}
              </div>
            </div>
          </>
        )}

        {/* Action Buttons */}
        <div style={{ display: 'flex', gap: '12px', flexDirection: 'column', marginTop: 'auto' }}>
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
            disabled={saving || !templateName || !templateKey || activeFields.length === 0}
            style={{
              padding: '14px',
              background: saving || !templateName || !templateKey || activeFields.length === 0
                ? '#9ca3af'
                : '#000',
              color: '#fff',
              border: 'none',
              borderRadius: '10px',
              cursor: saving || !templateName || !templateKey || activeFields.length === 0
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
                if (template && window.confirm(__('Template wirklich löschen?', 'bs-custom-mail'))) {
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
