import type { Metadata } from "next";
import "./globals.css";
import { AuthProvider } from "@/context/AuthContext";
import { RealtimeProvider } from "@/context/RealtimeContext";

export const metadata: Metadata = {
  title: "TaskGrid | Premium Task Management",
  description: "Experience the state-of-the-art task collaborative workspace with real-time SSE syncing, chunked uploads, and automated queues.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" data-theme="dark">
      <body>
        <AuthProvider>
          <RealtimeProvider>
            <div className="mesh-glow mesh-glow-1" />
            <div className="mesh-glow mesh-glow-2" />
            {children}
          </RealtimeProvider>
        </AuthProvider>
      </body>
    </html>
  );
}
