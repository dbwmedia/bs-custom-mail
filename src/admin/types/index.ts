/**
 * TypeScript types for BS Custom Mail Admin
 */

export interface Attachment {
	id: number;
	name: string;
	extension: string;
	size: string;
	icon: string;
}

export interface Template {
	id?: number;
	template_key: string;
	template_name: string;
	subject: string;
	header_text: string;
	content: string;
	footer_text: string;
	attachments: Attachment[] | string;
	is_active: boolean;
	created_at?: string;
	updated_at?: string;
}

export interface Settings {
	trigger_status: string;
	from_name: string;
	from_email: string;
}

export interface Stats {
	total_sent: number;
	total_failed: number;
	total_emails: number;
	success_rate: number;
	template_stats: TemplateStat[];
}

export interface TemplateStat {
	template_name: string;
	template_key: string;
	sent_count: number;
	failed_count: number;
}

export interface Activity {
	id: number;
	order_id: number;
	customer_email: string;
	product_name: string;
	template_key: string;
	status: 'sent' | 'failed' | 'template_not_found';
	sent_at: string;
}

export interface Notice {
	id: string;
	status: 'success' | 'error' | 'warning' | 'info';
	message: string;
}

export type ViewType = 'list' | 'create' | 'edit' | 'settings' | 'stats' | 'vouchers' | 'pdf-templates';

/**
 * Voucher types
 */
export interface Voucher {
	id: number;
	order_id: number;
	order_item_id: number;
	product_id: number;
	voucher_code: string;
	voucher_value: number;
	recipient_email: string;
	recipient_name: string;
	personal_message: string;
	pdf_path: string;
	expiry_date: string;
	status: 'active' | 'used' | 'cancelled';
	usage_count: number;
	created_at: string;
	used_at?: string;
}

export interface VoucherStats {
	total: number;
	active: number;
	used: number;
	cancelled: number;
	total_value: number;
}

export interface PDFTemplate {
	id?: number;
	template_name: string;
	template_key: string;
	attachment_id: number;
	attachment_url?: string;
	template_config: PDFTemplateConfig | string;
	font_size?: number;
	paper_size?: 'A4' | 'A5' | 'A6';
	orientation?: 'portrait' | 'landscape';
	background_color?: string;
	background_type?: 'color' | 'image';
	active_fields?: string[] | string;
	is_active?: boolean;
	created_at?: string;
	updated_at?: string;
}

export interface Position {
	x: number;
	y: number;
	fontSize?: number;
	visible?: boolean;
}

export interface PDFTemplateConfig {
	wert: Position;
	code: Position;
	name: Position;
	expiry: Position;
	adressant?: Position;
	notiz?: Position;
}

export interface PDFFieldDefinition {
	key: string;
	label: string;
	color: string;
	defaultPosition: Position;
	optional?: boolean;
}
