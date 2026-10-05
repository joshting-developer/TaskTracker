import nodemailer from 'nodemailer';
import { fail } from '../src/domain/model.js';

export function createSmtp(env = process.env, transportFactory = nodemailer.createTransport) {
  const configured = Boolean(env.SMTP_HOST && env.SMTP_FROM);
  return {
    status: () => ({ configured, mode: 'preview', provider: configured ? 'SMTP' : '尚未設定', canSend: false }),
    async verify() {
      if (!configured) fail('INVALID_INPUT', '請先在伺服器設定 SMTP_HOST 與 SMTP_FROM');
      const secure = env.SMTP_SECURE === 'true';
      const port = Number(env.SMTP_PORT || (secure ? 465 : 587));
      if (!Number.isInteger(port) || port < 1 || port > 65535) fail('INVALID_INPUT', 'SMTP_PORT 設定不正確');
      const transport = transportFactory({
        host: env.SMTP_HOST, port, secure, requireTLS: !secure,
        auth: env.SMTP_USER ? { user: env.SMTP_USER, pass: env.SMTP_PASSWORD } : undefined,
        connectionTimeout: 10000, greetingTimeout: 10000, socketTimeout: 15000,
        disableFileAccess: true, disableUrlAccess: true,
      });
      try {
        await transport.verify();
        return { verified: true, message: 'SMTP 連線與認證通過, 沒有寄出信件；寄件者與收件者仍需實寄驗證' };
      } catch { fail('SMTP_ERROR', 'SMTP 連線或認證失敗, 請檢查伺服器設定'); }
      finally { transport.close(); }
    },
  };
}
