# BondKonnect Mock Data Generation Plan

This document outlines the strategy for populating the BondKonnect database with realistic, Kenyan-market specific mock data. This data will facilitate testing of the platform's features, including bond trading, portfolio management, and administrative functions.

## Phase 1: Foundation Data (Users & Roles)

**Objective:** Establish the user base with distinct roles to simulate various actors in the bond market.

**Tasks:**
1.  **Admin User:** Create a super-admin account with full system access.
    *   *Role:* Admin (ID: 1)
    *   *Details:* System Administrator, realistic email/phone.
2.  **Issuer Profiles:** Create corporate and government issuer profiles.
    *   *Role:* Corporate/Issuer (ID: 4)
    *   *Entities:* Kenyan Government (Treasury), EABL, KenGen, Safaricom.
3.  **Investor Profiles:** Create diverse investor accounts.
    *   *Role:* Individual (ID: 2) & Broker (ID: 5)
    *   *Profiles:*
        *   High Net Worth Individual (aggressive portfolio).
        *   Retail Investor (conservative, government bonds).
        *   Institutional Broker (high volume trading).
    *   *Data:* Real-looking Kenyan National IDs, KRA PINs (if applicable in schema), and phone numbers (+254...).

## Phase 2: Market Instruments (Bonds & Primary Market)

**Objective:** Populate the system with tradeable instruments reflecting the actual Nairobi Securities Exchange (NSE) offerings.

**Tasks:**
1.  **Government Bonds (Treasury):**
    *   *Table:* `statstable` & `primarymarkettable`
    *   *Data:*
        *   **IFB1/2023/17:** Infrastructure Bond, tax-free, high coupon (~14%).
        *   **FXD1/2024/03:** Fixed Coupon Bond, standard taxation.
        *   **FXD1/2019/20:** Long-term bond.
    *   *Details:* Realistic Issue Dates, Maturity Dates (2020-2040), Coupon Rates (10% - 16%).
2.  **Corporate Bonds:**
    *   *Data:* EABL Medium Term Note, KenGen Infrastructure Bond.
3.  **Yield Curve Data:**
    *   *Table:* `ytmtable`
    *   *Data:* Mock yield curve points for 91-day, 182-day, 364-day, 2yr, 5yr, 10yr, 20yr tenors based on recent CBK averages.

## Phase 3: Market Activity (Quotes & Transactions)

**Objective:** Simulate a vibrant secondary market with historical and active trading data.

**Tasks:**
1.  **Order Book (Quotes):**
    *   *Table:* `quotebook`
    *   *Data:* Active Bids and Offers for the seeded bonds.
    *   *Logic:* Spreads of ~50-100 basis points. Volumes ranging from 50k to 50M KES.
2.  **Transaction History:**
    *   *Table:* `quotetransactions`
    *   *Data:* Executed trades over the last 2-3 years (2023-2025).
    *   *Flow:* Link specific Buyers and Sellers (from Phase 1) to specific Quotes.
    *   *Status:* Mix of Accepted, Rejected, and Pending trades.

## Phase 4: Financials & Portfolio

**Objective:** Reflect the financial impact of trades on user portfolios.

**Tasks:**
1.  **Portfolio Holdings:**
    *   *Table:* `portfolio` / `portfoliodata`
    *   *Logic:* Aggregate executed BUY trades minus SELL trades.
    *   *Metrics:* Calculate Weighted Average Price (WAP) and unrealized P&L based on current market yields.
2.  **Payments:**
    *   *Table:* `mpesatransactions` / `billingdetails`
    *   *Data:* Simulation of settlement payments.
    *   *Details:* M-Pesa transaction codes (e.g., QK...), amounts matching trade values + commissions.

## Phase 5: System Logs & Audit

**Objective:** Populate administrative logs for security and audit testing.

**Tasks:**
1.  **Activity Logs:**
    *   *Table:* `activitylogs`
    *   *Events:* User Logins, Password Changes, Trade Placements, Report Generations.
    *   *Volume:* ~100 entries distributed over time.
2.  **Notifications:**
    *   *Table:* `notificationservices` / `emaillogs` / `smslogs`
    *   *Data:* "Trade Executed", "Account Verified", "Market Alert" messages linked to users.

---

**Implementation Strategy:**
We will create a master Seeder class `MockDataSeeder` in `bondkonnect_api/database/seeders/` that orchestrates individual seeders for each phase. This ensures referential integrity (e.g., ensuring a User exists before assigning them a Trade).
