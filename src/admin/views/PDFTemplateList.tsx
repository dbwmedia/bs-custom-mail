/**
 * PDF Template List - Modern Black/White Design
 */
import { useState, useEffect } from '@wordpress/element'
import { __ } from '@wordpress/i18n'
import apiFetch from '@wordpress/api-fetch'
import { PDFTemplate, ViewType } from '../types'
import { PDFTemplateEditor } from './PDFTemplateEditor'

interface Props {
  onNavigate: (view: ViewType) => void
}

export function PDFTemplateList({ onNavigate }: Props) {
  const [templates, setTemplates] = useState<PDFTemplate[]>([])
  const [loading, setLoading] = useState(true)
  const [editingTemplate, setEditingTemplate] = useState<
    PDFTemplate | undefined
  >(undefined)
  const [isCreating, setIsCreating] = useState(false)

  const fetchTemplates = async () => {
    try {
      setLoading(true)
      const response = await apiFetch({
        path: 'bs-custom-mail/v1/pdf-templates',
      })
      setTemplates((response as { templates: PDFTemplate[] }).templates || [])
    } catch (error) {
      console.error('Error fetching PDF templates:', error)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchTemplates()
  }, [])

  const handleSave = () => {
    setEditingTemplate(undefined)
    setIsCreating(false)
    fetchTemplates()
  }

  const handleDelete = async (template: PDFTemplate) => {
    try {
      await apiFetch({
        path: `bs-custom-mail/v1/pdf-templates/${template.id}`,
        method: 'DELETE',
      })
      fetchTemplates()
    } catch (error) {
      console.error('Error deleting template:', error)
      alert(__('Fehler beim Löschen', 'bs-custom-mail'))
    }
  }

  if (isCreating) {
    return (
      <PDFTemplateEditor
        mode='create'
        onCancel={() => setIsCreating(false)}
        onSave={handleSave}
      />
    )
  }

  if (editingTemplate) {
    return (
      <PDFTemplateEditor
        template={editingTemplate}
        mode='edit'
        onCancel={() => setEditingTemplate(undefined)}
        onSave={handleSave}
        onDelete={handleDelete}
      />
    )
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
        }}
      >
        <button
          onClick={() => onNavigate('vouchers')}
          style={{
            padding: '10px 16px',
            background: '#f3f4f6',
            color: '#374151',
            border: 'none',
            borderRadius: '8px',
            cursor: 'pointer',
            fontWeight: 500,
            display: 'flex',
            alignItems: 'center',
            gap: '8px',
            fontSize: '14px',
          }}
        >
          ← {__('Zurück zu Gutscheinen', 'bs-custom-mail')}
        </button>
        <button
          onClick={() => setIsCreating(true)}
          style={{
            padding: '12px 20px',
            background: '#000',
            color: '#fff',
            border: 'none',
            borderRadius: '10px',
            cursor: 'pointer',
            fontWeight: 600,
            display: 'flex',
            alignItems: 'center',
            gap: '8px',
            fontSize: '14px',
          }}
        >
          + {__('Neues Template', 'bs-custom-mail')}
        </button>
      </div>

      {loading ? (
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))',
            gap: '20px',
          }}
        >
          {[1, 2, 3].map((i) => (
            <div
              key={i}
              style={{
                background: '#fff',
                borderRadius: '12px',
                border: '1px solid #e5e7eb',
                height: '200px',
                animation: 'pulse 2s infinite',
              }}
            />
          ))}
        </div>
      ) : templates.length === 0 ? (
        <div
          style={{
            background: '#fff',
            borderRadius: '16px',
            border: '1px solid #e5e7eb',
            padding: '64px 32px',
            textAlign: 'center',
          }}
        >
          <div style={{ marginBottom: '24px' }}>
            <div
              style={{
                width: '80px',
                height: '80px',
                background: '#f3f4f6',
                borderRadius: '20px',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                margin: '0 auto',
                fontSize: '36px',
              }}
            >
              📄
            </div>
          </div>
          <h3
            style={{
              fontSize: '20px',
              fontWeight: 700,
              color: '#000',
              marginBottom: '8px',
            }}
          >
            {__('Keine Templates vorhanden', 'bs-custom-mail')}
          </h3>
          <p
            style={{ fontSize: '15px', color: '#6b7280', marginBottom: '32px' }}
          >
            {__(
              'Erstellen Sie Ihr erstes PDF Template für Gutscheine.',
              'bs-custom-mail',
            )}
          </p>
          <button
            onClick={() => setIsCreating(true)}
            style={{
              padding: '14px 28px',
              background: '#000',
              color: '#fff',
              border: 'none',
              borderRadius: '10px',
              cursor: 'pointer',
              fontWeight: 600,
              fontSize: '15px',
            }}
          >
            {__('Erstes Template erstellen', 'bs-custom-mail')}
          </button>
        </div>
      ) : (
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))',
            gap: '20px',
          }}
        >
          {templates.map((template) => {
            const isImage = template.attachment_url?.match(
              /\.(jpg|jpeg|png|webp)$/i,
            )
            return (
              <div
                key={template.id}
                onClick={() => setEditingTemplate(template)}
                style={{
                  background: '#fff',
                  borderRadius: '16px',
                  border: '1px solid #e5e7eb',
                  overflow: 'hidden',
                  cursor: 'pointer',
                  transition: 'all 0.2s',
                }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.borderColor = '#000'
                  e.currentTarget.style.transform = 'translateY(-2px)'
                  e.currentTarget.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)'
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.borderColor = '#e5e7eb'
                  e.currentTarget.style.transform = 'none'
                  e.currentTarget.style.boxShadow = 'none'
                }}
              >
                <div
                  style={{
                    aspectRatio: '4/3',
                    background: '#f9fafb',
                    position: 'relative',
                  }}
                >
                  {template.attachment_url ? (
                    isImage ? (
                      <img
                        src={template.attachment_url}
                        alt={template.name}
                        style={{
                          width: '100%',
                          height: '100%',
                          objectFit: 'cover',
                        }}
                      />
                    ) : (
                      <div
                        style={{
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          height: '100%',
                          background: '#f3f4f6',
                          fontSize: '48px',
                        }}
                      >
                        ⊞
                      </div>
                    )
                  ) : (
                    <div
                      style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        height: '100%',
                        background: '#f3f4f6',
                        fontSize: '48px',
                      }}
                    >
                      🖼️
                    </div>
                  )}
                </div>
                <div style={{ padding: '20px' }}>
                  <h3
                    style={{
                      fontSize: '16px',
                      fontWeight: 700,
                      color: '#000',
                      marginBottom: '4px',
                    }}
                  >
                    {template.name}
                  </h3>
                  <p style={{ fontSize: '13px', color: '#6b7280', margin: 0 }}>
                    {isImage ? 'Bild' : 'PDF'}
                  </p>
                </div>
              </div>
            )
          })}
        </div>
      )}

      <style>{`
				@keyframes pulse {
					0%, 100% { opacity: 1; }
					50% { opacity: 0.5; }
				}
			`}</style>
    </div>
  )
}
