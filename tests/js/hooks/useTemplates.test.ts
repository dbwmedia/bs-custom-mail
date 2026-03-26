/**
 * useTemplates hook tests
 */
import { renderHook, waitFor } from '@testing-library/react';
import { useTemplates } from '../../../src/admin/hooks/useTemplates';
import apiFetch from '@wordpress/api-fetch';

jest.mock('@wordpress/api-fetch');

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

describe('useTemplates', () => {
	const mockTemplates = [
		{
			id: 1,
			template_key: 'sbf_see',
			template_name: 'SBF See',
			subject: 'Ihre Kursbuchung',
			header_text: 'Header',
			content: 'Content',
			footer_text: 'Footer',
			attachments: [],
			is_active: true,
		},
	];

	beforeEach(() => {
		jest.clearAllMocks();
	});

	it('should fetch templates on mount', async () => {
		mockedApiFetch.mockResolvedValue(mockTemplates);

		const { result } = renderHook(() => useTemplates());

		expect(result.current.isLoading).toBe(true);

		await waitFor(() => {
			expect(result.current.isLoading).toBe(false);
		});

		expect(result.current.templates).toEqual(mockTemplates);
		expect(apiFetch).toHaveBeenCalledWith({ path: '/bs-custom-mail/v1/templates' });
	});

	it('should handle fetch error', async () => {
		mockedApiFetch.mockRejectedValue(new Error('Network error'));

		const { result } = renderHook(() => useTemplates());

		await waitFor(() => {
			expect(result.current.isLoading).toBe(false);
		});

		expect(result.current.error).toBe('Network error');
	});

	it('should create template', async () => {
		mockedApiFetch
			.mockResolvedValueOnce(mockTemplates)
			.mockResolvedValueOnce({ ...mockTemplates[0], id: 2 });

		const { result } = renderHook(() => useTemplates());

		await waitFor(() => {
			expect(result.current.isLoading).toBe(false);
		});

		const newTemplate = {
			template_key: 'new_template',
			template_name: 'New Template',
			subject: 'New Subject',
			header_text: '',
			content: '',
			footer_text: '',
			attachments: [],
			is_active: true,
		};

		await result.current.createTemplate(newTemplate);

		expect(apiFetch).toHaveBeenCalledWith({
			path: '/bs-custom-mail/v1/templates',
			method: 'POST',
			data: {
				...newTemplate,
				attachments: [],
			},
		});
	});

	it('should update template', async () => {
		mockedApiFetch
			.mockResolvedValueOnce(mockTemplates)
			.mockResolvedValueOnce({ ...mockTemplates[0], subject: 'Updated Subject' });

		const { result } = renderHook(() => useTemplates());

		await waitFor(() => {
			expect(result.current.isLoading).toBe(false);
		});

		await result.current.updateTemplate('sbf_see', { subject: 'Updated Subject' });

		expect(apiFetch).toHaveBeenCalledWith({
			path: '/bs-custom-mail/v1/templates/sbf_see',
			method: 'PUT',
			data: { subject: 'Updated Subject' },
		});
	});

	it('should delete template', async () => {
		mockedApiFetch
			.mockResolvedValueOnce(mockTemplates)
			.mockResolvedValueOnce({ deleted: true });

		const { result } = renderHook(() => useTemplates());

		await waitFor(() => {
			expect(result.current.isLoading).toBe(false);
		});

		await result.current.deleteTemplate('sbf_see');

		expect(apiFetch).toHaveBeenCalledWith({
			path: '/bs-custom-mail/v1/templates/sbf_see',
			method: 'DELETE',
		});
	});

	it('should send test email', async () => {
		mockedApiFetch
			.mockResolvedValueOnce(mockTemplates)
			.mockResolvedValueOnce({ success: true, message: 'Test email sent' });

		const { result } = renderHook(() => useTemplates());

		await waitFor(() => {
			expect(result.current.isLoading).toBe(false);
		});

		const response = await result.current.sendTestEmail('sbf_see', 'test@example.com');

		expect(response.success).toBe(true);
		expect(apiFetch).toHaveBeenCalledWith({
			path: '/bs-custom-mail/v1/templates/sbf_see/test',
			method: 'POST',
			data: { email: 'test@example.com' },
		});
	});
});
