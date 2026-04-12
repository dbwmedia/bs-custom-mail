/**
 * Main Admin App Component - Modern Black/White Design
 */
import { useState } from '@wordpress/element'
import { __ } from '@wordpress/i18n'
import {
  TemplateList,
  TemplateEditor,
  VoucherList,
  PDFTemplateList,
  Settings,
} from './views'
import { ViewType } from './types'

/**
 * Get initial view from URL parameters
 */
function getInitialViewFromUrl(): { view: ViewType; templateKey?: string } {
  const urlParams = new URLSearchParams(window.location.search)
  const page = urlParams.get('page')
  const action = urlParams.get('action')
  const templateKey = urlParams.get('template') || undefined

  if (page === 'bs-custom-mail-vouchers') {
    return { view: 'vouchers' }
  }

  if (page === 'bs-custom-mail-pdf-templates') {
    return { view: 'pdf-templates' }
  }

  if (action === 'edit' && templateKey) {
    return { view: 'edit', templateKey }
  }
  if (action === 'create') {
    return { view: 'create' }
  }

  return { view: 'list' }
}

/**
 * Update URL without reloading the page
 */
function updateUrl(view: ViewType, templateKey?: string) {
  const url = new URL(window.location.href)

  url.searchParams.delete('action')
  url.searchParams.delete('template')

  if (view === 'edit' && templateKey) {
    url.searchParams.set('action', 'edit')
    url.searchParams.set('template', templateKey)
  } else if (view === 'create') {
    url.searchParams.set('action', 'create')
  }

  window.history.replaceState({}, '', url.toString())
}

export function App() {
  const initialState = getInitialViewFromUrl()
  const [currentView, setCurrentView] = useState<ViewType>(initialState.view)
  const [selectedTemplateKey, setSelectedTemplateKey] = useState<
    string | undefined
  >(initialState.templateKey)

  const handleNavigate = (view: ViewType, templateKey?: string) => {
    setCurrentView(view)
    if (templateKey) {
      setSelectedTemplateKey(templateKey)
    }
    updateUrl(view, templateKey)
  }

  const handleTabNavigate = (view: ViewType) => {
    setCurrentView(view)
    updateUrl(view)
  }

  const isTemplateView =
    currentView === 'list' || currentView === 'create' || currentView === 'edit'
  const isVoucherView =
    currentView === 'vouchers' || currentView === 'pdf-templates'
  const isSettingsView = currentView === 'settings'

  const renderContent = () => {
    switch (currentView) {
      case 'create':
        return (
          <TemplateEditor
            mode='create'
            onNavigate={handleNavigate}
          />
        )
      case 'edit':
        return (
          <TemplateEditor
            mode='edit'
            templateKey={selectedTemplateKey}
            onNavigate={handleNavigate}
          />
        )
      case 'vouchers':
        return <VoucherList onNavigate={handleNavigate} />
      case 'pdf-templates':
        return <PDFTemplateList onNavigate={handleNavigate} />
      case 'settings':
        return <Settings />
      case 'list':
      default:
        return <TemplateList onNavigate={handleNavigate} />
    }
  }

  return (
    <div
      style={{
        padding: '24px',
        background: '#f9fafb',
        minHeight: 'calc(100vh - 32px)',
      }}
    >
      <div style={{ maxWidth: '1400px', margin: '0 auto' }}>
        <div style={{ marginBottom: '32px' }}>
          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              marginBottom: '24px',
            }}
          >
            <h1
              style={{
                fontSize: '32px',
                fontWeight: 800,
                margin: 0,
                color: '#000',
                letterSpacing: '-1px',
              }}
            >
              Bootsschule Mail
            </h1>
          </div>

          <div
            style={{
              display: 'flex',
              gap: '4px',
              borderBottom: '2px solid #e5e7eb',
            }}
          >
            <button
              onClick={() => handleTabNavigate('list')}
              style={{
                padding: '16px 24px',
                background: 'transparent',
                border: 'none',
                borderBottom: isTemplateView
                  ? '2px solid #000'
                  : '2px solid transparent',
                fontWeight: isTemplateView ? 700 : 500,
                color: isTemplateView ? '#000' : '#6b7280',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                fontSize: '15px',
                marginBottom: '-2px',
                transition: 'all 0.2s',
              }}
            >
              <span style={{ fontSize: '18px' }}>📧</span>
              {__('E-Mail Templates', 'bs-custom-mail')}
            </button>
            <button
              onClick={() => handleTabNavigate('vouchers')}
              style={{
                padding: '16px 24px',
                background: 'transparent',
                border: 'none',
                borderBottom: isVoucherView
                  ? '2px solid #000'
                  : '2px solid transparent',
                fontWeight: isVoucherView ? 700 : 500,
                color: isVoucherView ? '#000' : '#6b7280',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                fontSize: '15px',
                marginBottom: '-2px',
                transition: 'all 0.2s',
              }}
            >
              <span style={{ fontSize: '18px' }}>🎁</span>
              {__('Gutscheine', 'bs-custom-mail')}
            </button>
            <button
              onClick={() => handleTabNavigate('settings')}
              style={{
                padding: '16px 24px',
                background: 'transparent',
                border: 'none',
                borderBottom: isSettingsView
                  ? '2px solid #000'
                  : '2px solid transparent',
                fontWeight: isSettingsView ? 700 : 500,
                color: isSettingsView ? '#000' : '#6b7280',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                fontSize: '15px',
                marginBottom: '-2px',
                transition: 'all 0.2s',
              }}
            >
              <span style={{ fontSize: '18px' }}>⚙️</span>
              {__('Einstellungen', 'bs-custom-mail')}
            </button>
          </div>
        </div>

        <div style={{ animation: 'fadeIn 0.3s ease' }}>{renderContent()}</div>
      </div>

      <style>{`
				@keyframes fadeIn {
					from { opacity: 0; transform: translateY(10px); }
					to { opacity: 1; transform: translateY(0); }
				}
			`}</style>
    </div>
  )
}
